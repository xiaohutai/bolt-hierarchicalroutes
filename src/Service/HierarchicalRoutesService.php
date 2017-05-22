<?php

namespace Bolt\Extension\TwoKings\HierarchicalRoutes\Service;

use Bolt\Cache;
use Bolt\Extension\TwoKings\HierarchicalRoutes\Config\Config;
use Bolt\Storage\EntityManager;
use Bolt\Storage\Query\Query;
use Monolog\Logger;
use Silex\Application;

/**
 * Helper class for hierarchical routes extension.
 *
 * @author Xiao-Hu Tai <xiao@twokings.nl>
 */
class HierarchicalRoutesService
{
    /** @var Config $config */
    private $config;

    /** @var \Bolt\Config $boltConfig */
    private $boltConfig;

    /** @var EntityManager $storage */
    private $storage;

    /** @var Query $query */
    private $query;

    /** @var Cache $cache */
    private $cache;

    /** @var Logger $logger */
    private $logger;

    /** @var int $cacheDuration The cache duration in seconds */
    private $cacheDuration = 600;

    /** @var string $cachePrefix */
    private $cachePrefix = 'hierarchicalroutes-';

    /** @var string[] $properties */
    private $properties = [
        'parents',
        'children',
        'slugs',
        'recordRoutes',
        'listingRoutes',
        'contenttypeRules',
    ];

    /** @var string[] $parents A mapping from items to their parent */
    private $parents = [];

    /** @var string[] $children A mapping from items to their children */
    private $children = [];

    /** @var string[] $slugs A mapping from items to their simple slug (i.e. not pre-pended with parents' slugs) */
    private $slugs = [];

    /** @var string[] $recordRoutes A mapping of records to generated routes (i.e. pre-pended with parents' slugs) */
    private $recordRoutes = [];

    /** @var string[] $listingRoutes A mapping of listing pages to generated routes (i.e. pre-pended with parents' slugs) */
    private $listingRoutes = [];

    /** @var string[] $contenttypeRules A mapping of parent nodes to arrays of contenttypeslugs */
    private $contenttypeRules = [];

    /**
     * Constructor
     *
     * @param Config        $config
     * @param \Bolt\Config  $boltConfig
     * @param EntityManager $storage
     * @param Query         $query
     * @param Cache         $cache
     * @param Logger        $logger
     */
    public function __construct(
        Config        $config,
        \Bolt\Config  $boltConfig,
        EntityManager $storage,
        Query         $query,
        Cache         $cache,
        Logger        $logger
    )
    {
        $this->config     = $config;
        $this->boltConfig = $boltConfig;
        $this->storage    = $storage;
        $this->query      = $query;
        $this->cache      = $cache;
        $this->logger     = $logger;

        $this->cacheDuration = $this->config->get('cache/duration', 10) * 60;

        $this->build();

        /*
        dump($this->parents);
        dump($this->children);
        dump($this->slugs);
        //*/

        /*
        dump($this->recordRoutes);
        dump($this->listingRoutes);
        dump($this->contenttypeRules);
        //*/
    }

    /**
     * Builds the hierarchy based on the menu and simple rules.
     *
     * @param bool $useCache Whether to fetch information from cache or not.
     */
    public function build($useCache = true)
    {
        if ($this->config->get('cache/enabled', true) && $useCache && $this->fromCache()) {
            return;
        }

        if (!$useCache) {
            $this->logger->info('Ignoring cache. Rebuilding hierarchical data.', ['event' => 'extension']);
        } else {
            $this->logger->info('Cache expired or not found. Rebuilding hierarchical data.', ['event' => 'extension']);
        }

        $menu = $this->config->get('menu');
        if (is_array($menu)) {
            foreach ($menu as $menuName) {
                $this->importMenu($menuName);
            }
        } else {
            $this->importMenu($menu);
        }

        $rules = $this->config->get('rules', []);
        if (is_array($rules)) {
            $this->importRules($rules);
        }

        if ($this->config->get('cache/enabled', true)) {
            $this->toCache();
        }
    }

    /**
     * @return `true` if successful, otherwise `false`.
     */
    private function fromCache()
    {
        foreach ($this->properties as $property) {
            $this->$property = $this->cache->fetch($this->cachePrefix . $property);

            if ($this->$property === false) {
                // Reset all properties and import
                foreach ($this->properties as $prop) {
                    $this->$prop = [];
                }
                return false;
            }
        }

        // This will flood the logger system.
        // $this->logger->info('Using cached data', ['event' => 'extension']);

        return true;
    }

    /**
     * Stores all data to cache.
     */
    private function toCache()
    {
        foreach ($this->properties as $property) {
            $this->cache->save(
                $this->cachePrefix . $property,
                $this->$property,
                $this->cacheDuration
            );
        }
    }

    /**
     * @param string $identifier
     */
    private function importMenu($identifier = 'main')
    {
        $menu = $this->boltConfig->get('menu/' . $identifier, []);

        foreach ($menu as $item) {
            $this->importMenuItem($item);
        }
    }

    /**
     * Import an individual menu item (record/page) and recursively adds its
     * children.
     *
     * @param array  $item   A menu item
     * @param string $parent The menu items's parent's route (pre-pended with parents' slugs)
     */
    private function importMenuItem($item, $parent = null)
    {
        $content = false;

        if (isset($item['path'])) {
            /** @var \Bolt\Legacy\Content $content */
            $content = $this->storage->getContent($item['path'], ['hydrate' => false]);
        }

        // Only items with records can be in a hierarchy, otherwise it doesn't make much sense
        if ($content && !is_array($content)) {
            $id          = $content->id;
            $slug        = $content->values['slug'];
            $contenttype = $content->contenttype['slug']; // $content->contenttype['singular_slug'];


            // 'overwrite-duplicates'
            if (!isset($this->slugs["$contenttype/$id"]) || $this->config->get('settings/overwrite-duplicates', true)) {

                $oldParent = isset($this->parents["$contenttype/$id"]) ? $this->parents["$contenttype/$id"] : null;
                if ($oldParent) {
                    $this->children[$oldParent] = array_filter($this->children[$oldParent], function($v, $k) {
                        return $v !== "$contenttype/$id";
                    }, ARRAY_FILTER_USE_BOTH);
                }

                $this->parents["$contenttype/$id"] = $parent;
                $this->slugs["$contenttype/$id"]   = $slug;
                $this->recordRoutes["$contenttype/$id"]  = $parent ? $this->recordRoutes[$parent] . '/' . $slug : $slug;

                if ($parent) {
                    $this->children[$parent][] = "$contenttype/$id"; // but also add links and other items ???
                }
            }

            if (isset($item['submenu'])) {
                foreach ($item['submenu'] as $subitem) {
                    $this->importMenuItem($subitem, "$contenttype/$id");
                }
            }
        }
        elseif (is_array($content)) {
            $path = $item['path'];
            $path = trim($path, '/');

            // 'overwrite-duplicates'
            if (!isset($this->slugs[$path]) || $this->config->get('settings/overwrite-duplicates', true)) {
                $this->parents[$path] = $parent;
                $this->slugs[$path]   = $path;
                $this->listingRoutes[$path]  = $parent ? $this->recordRoutes[$parent] . '/' . $path : $path;
            }

            if (isset($item['submenu'])) {
                foreach ($item['submenu'] as $subitem) {
                    $this->importMenuItem($subitem, $path);
                }
            }
        }
        else {
            // log errors if not found or if skipped (reason)
        }
    }

    /**
     * Import an array of rules.
     *
     * @param array $rules
     */
    private function importRules(array $rules)
    {
        foreach ($rules as $rule) {
            $this->importRule($rule['type'], $rule['params']);
        }
    }

    /**
     * Import an individual rule.
     *
     * @param string $type  One of 'contenttype'|'query', todo CONST
     * @param array  $params Additional parameters
     */
    private function importRule($type, $params)
    {
        $content = $this->storage->getContent($params['parent'], ['hydrate' => false]);

        switch ($type) {
            case 'contenttype':
                if ($content && !is_array($content)) {
                    $contenttypeslug = $content->contenttype['slug'];
                    $id = $content->id;
                    $this->contenttypeRules["$contenttypeslug/$id"][] = $params['slug'];
                } elseif (is_array($content)) {
                    $path = $params['parent'];
                    $path = trim($path, '/');
                    $this->contenttypeRules[$path][] = $params['slug'];
                }
                break;

            case 'query':
                $parent = false;
                if ($content && !is_array($content)) {
                    $contenttypeslug = $content->contenttype['slug'];
                    $id = $content->id;
                    $parent = "$contenttypeslug/$id";
                } elseif (is_array($content)) {
                    $path = $params['parent'];
                    $path = trim($path, '/');
                    $parent = $path;
                }

                if ($parent) {
                    /** @var \Bolt\Storage\Entity\Content[] $items */
                    $items = $this->query->getContent($params['query'], $params['parameters']);
                    foreach ($items as $item) {
                        $contenttypeslug = $item->getContenttype()['slug'];
                        $id = $item->getId();
                        $slug = $item->getSlug();

                        // 'overwrite-duplicates'
                        if (!isset($this->slugs["$contenttypeslug/$id"]) || $this->config->get('settings/overwrite-duplicates', true)) {
                            $oldParent = isset($this->parents["$contenttypeslug/$id"]) ? $this->parents["$contenttypeslug/$id"] : null;
                            if ($oldParent) {
                                $this->children[$oldParent] = array_filter($this->children[$oldParent], function($v, $k) use ($contenttypeslug, $id) {
                                    return $v !== "$contenttypeslug/$id";
                                }, ARRAY_FILTER_USE_BOTH);
                            }

                            $this->parents["$contenttypeslug/$id"] = $parent;
                            $this->children[$parent][]             = "$contenttypeslug/$id";
                            $this->slugs["$contenttypeslug/$id"]   = $slug;
                        }

                        if (is_array($content)) {
                            $this->listingRoutes["$contenttypeslug/$id"]  = $this->recordRoutes[$parent] . '/' . $slug;
                        } else {
                            $this->recordRoutes["$contenttypeslug/$id"]  = $this->recordRoutes[$parent] . '/' . $slug;
                        }
                    }
                }
                break;

            default:
                // log an error invalid rule type found
        }
    }

    /**
     * Returns the parent of the current record, otherwise `null`.
     *
     * @return The current record's parent.
     */
    public function getParent($record)
    {
        $contenttypeslug = $record->contenttype['slug'];
        $id = $record->id;

        $parent = isset($this->parents["$contenttypeslug/$id"]) ? $this->parents["$contenttypeslug/$id"] : null;

        return $this->hydrateRecord($parent);
    }

    /**
     * Returns an array of all the parents of the current record. This is useful
     * for breadcrumbs: iterate over `getParents(record)|reverse`.
     *
     * [ parent, grandparent, great-grandparent, ... ]
     *
     * @return An array of the current record's parents.
     */
    public function getParents($record)
    {
        $parents = [];
        $contenttypeslug = $record->contenttype['slug'];
        $id = $record->id;
        $parent = "$contenttypeslug/$id";

        while (isset($this->parents[$parent])) {
            $parent = $this->parents[$parent];
            if ($parent !== null) {
                $parents[] = $parent;
            }
        }

        return array_map([$this, 'hydrateRecord'], $parents);
    }

    /**
     * Returns an array of all the children of the current record.
     */
    public function getChildren($record)
    {
        $contenttypeslug = $record->contenttype['slug'];
        $id = $record->id;

        if (isset($this->children["$contenttypeslug/$id"])) {
            return array_map(
                [$this, 'hydrateRecord'],
                $this->children["$contenttypeslug/$id"]
            );
        }

        return [];

        // PHP7:
        // return $this->children["$contenttypeslug/$id"] ?? [];
    }

    /**
     * Returns siblings but not myself
     */
    public function getSiblings($record)
    {
        $contenttypeslug = $record->contenttype['slug'];
        $id = $record->id;

        $parent = $this->getParent($record);

        $siblings = array_filter($this->parents, function($v, $k) use ($parent, $contenttypeslug, $id) {
            return $v === $parent && $k !== "$contenttypeslug/$id";
        }, ARRAY_FILTER_USE_BOTH);

        return array_map(
            [$this, 'hydrateRecord'],
            array_values($siblings)
        );
    }

    /**
     * Go from a string like 'entry/1' to a real record
     *
     * @param string $item
     * @return \Bolt\Legacy\Content
     */
    private function hydrateRecord($item)
    {
        // return array_map([$this, 'hydrateRecord'], $array);
        // return $this->hydrateRecord($item);
        if ($item) {
            return $this->storage->getContent($item);
        } else {
            return $item; // or null
        }
    }

    /**
     *
     */
    public function getParentLinkForContentType($contenttypeslug)
    {
        foreach ($this->contenttypeRules as $parent => $contenttypes) {
            if (in_array($contenttypeslug, $contenttypes)) {
                return $this->recordRoutes[$parent];
            }
        }

        return null;
    }

    /**
     *
     */
    public function getPotentialParents()
    {
        $potentials = [];
        foreach(array_keys($this->contenttypeRules) as $parent) {
            $potentials[] = $this->recordRoutes[$parent];
        }
        return $potentials;
    }

    /**
     *
     */
    public function getRecordRoutes()
    {
        return $this->recordRoutes;
    }

    /**
     *
     */
    public function getListingRoutes()
    {
        return $this->listingRoutes;
    }

    /**
     *
     */
    public function getContentTypeRules()
    {
        return $this->contenttypeRules;
    }
}
