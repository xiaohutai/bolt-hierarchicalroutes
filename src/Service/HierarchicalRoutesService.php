<?php

namespace Bolt\Extension\TwoKings\HierarchicalRoutes\Service;

use Bolt\Extension\TwoKings\HierarchicalRoutes\Config\Config;
use Bolt\Filesystem\Manager;
use Bolt\Storage\EntityManagerInterface;
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

    /** @var EntityManagerInterface $storage */
    private $storage;

    /** @var Query $query */
    private $query;

    /** @var Manager $filesystem */
    private $filesystem;

    /** @var Logger $logger */
    private $logger;

    /** @var string $cachePath */
    private $cachePath = 'config://extensions/hierarchicalroutes';

    /** @var string[] $properties */
    private $properties = [
        'parents',
        'children',
        'slugs',
        'recordRoutes',
        'listingRoutes',
        'contenttypeRules',
    ];

    /** @var bool Specifies whether the structures are already built */
    private $done = false;

    /** @var bool An internal flag to prevent multiple `rebuild` calls within a single request */
    private $rebuilt = false;

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
     * @param Config                  $config
     * @param \Bolt\Config            $boltConfig
     * @param EntityManagerInterface  $storage
     * @param Query                   $query
     * @param Manager                 $filesystem
     * @param Logger                  $logger
     */
    public function __construct(
        Config                  $config,
        \Bolt\Config            $boltConfig,
        EntityManagerInterface  $storage,
        Query                   $query,
        Manager                 $filesystem,
        Logger                  $logger
    )
    {
        $this->config     = $config;
        $this->boltConfig = $boltConfig;
        $this->storage    = $storage;
        $this->query      = $query;
        $this->filesystem = $filesystem;
        $this->logger     = $logger;

        /**
         * For _earlier_ requests, such as:
         * - \Bolt\Extension\TwoKings\HierarchicalRoutes\ControllerRequirement
         * - \Bolt\Extension\TwoKings\HierarchicalRoutes\Routing\HierarchicalUrlGenerator
         *
         * It is generally not a good idea to use the database. So reading from
         * cache, gives us at least something to work with.
         */
        $this->done = $this->fromCache();
    }

    /**
     * Only for debugging purposes.
     */
    private function dump()
    {
        dump($this->parents);
        dump($this->children);
        dump($this->slugs);

        dump($this->recordRoutes);
        dump($this->listingRoutes);
        dump($this->contenttypeRules);
    }

    /**
     * Builds the hierarchy based on the menu and simple rules.
     *
     * This function used to be called in the constructor. But since Bolt3.3, it
     * is called via `Listener\KernelEventListener`.
     *
     * @param bool $useCache Whether to fetch information from cache or not.
     */
    public function build($useCache = true)
    {
        // Always rebuild if `hierarchicalroutes.twokings.yml` or `menu.yml` has
        // been modified
        $needRebuild = $this->needRebuildBasedOnTimestamps();

        // The constructor has already imported the correct data from cache.
        if ($useCache && !$needRebuild && $this->done) {
            return;
        }

        // Always reset all properties when rebuilding.
        $this->reset();


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

        $this->toCache();
    }

    /**
     * Helper function to rebuild the hierarchy structure bypassing the cache.
     */
    public function rebuild()
    {
        if (! $this->rebuilt) {
            $this->build(false);
            $this->rebuilt = true;
        }
    }

    /**
     * Resets all the data currently stored, used when rebuilding the hierarchy
     * structure.
     */
    private function reset()
    {
        foreach ($this->properties as $prop) {
            $this->$prop = [];
        }
    }

    /**
     * @return boolean
     */
    private function needRebuildBasedOnTimestamps()
    {
        $path = sprintf('config://extensions/hierarchicalroutes/%s.json', $this->properties[0]);

        if ( $this->filesystem->has($path) ) {
            /** @var \Bolt\Filesystem\Handler\YamlFile $configYaml */
            $configYaml = $this->filesystem->get('config://extensions/hierarchicalroutes.twokings.yml');

            /** @var \Bolt\Filesystem\Handler\YamlFile $menuYaml */
            $menuYaml = $this->filesystem->get('config://menu.yml');

            /** @var \Bolt\Filesystem\Handler\JsonFile $json */
            $json = $this->filesystem->get($path);

            $configTimestamp = $configYaml->getTimestamp();
            $menuTimestamp   = $menuYaml->getTimestamp();
            $jsonTimestamp   = $json->getTimestamp();

            return ($jsonTimestamp < $configTimestamp) || ($jsonTimestamp < $menuTimestamp);
        }

        // If no cache file has been found, then we need to rebuild anyways.
        return true;
    }

    /**
     * @return `true` if successful, otherwise `false`.
     */
    private function fromCache()
    {
        foreach ($this->properties as $property) {

            $this->$property = false;
            $fullPath = sprintf("%s/%s.json", $this->cachePath, $property);

            if ($this->filesystem->has($fullPath)) {
                $this->$property = json_decode($this->filesystem->read($fullPath), true);
            }

            if ($this->$property === false) {
                // Reset all properties and import if any of the properties are
                // failed to be set properly.
                $this->reset();
                return false;
            }
        }

        return true;
    }

    /**
     * Stores all data to cache.
     */
    private function toCache()
    {
        $this->filesystem->createDir($this->cachePath);

        foreach ($this->properties as $property) {
            $this->filesystem->put(
                sprintf("%s/%s.json", $this->cachePath, $property),
                json_encode($this->$property)
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
        if (isset($item['path']) && $item['path'] != 'homepage') {
            /** @var \Bolt\Legacy\Content $content */
            $content = $this->storage->getContent($item['path'], ['hydrate' => false]);
        }

        // Only items with records can be in a hierarchy, otherwise it doesn't make much sense
        if ($content && !is_array($content)) {

            $id           = $content->id;
            $slug         = $content->values['slug'];
            $originalSlug = $content->values['slug'];
            $contenttype  = $content->contenttype['slug'];

            // 'override-slugs'
            $overrideSlugs = $this->config->get('settings/override-slugs', false);
            if ($overrideSlugs && isset($item['override']) && !empty($item['override'])) {
                $slug = $item['override'];
            }

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
                $this->recordRoutes["$contenttype/$originalSlug"] = $this->recordRoutes["$contenttype/$id"];

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
                        // Case I: Overwrite duplicates
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

                            if (is_array($content)) {
                                $this->listingRoutes["$contenttypeslug/$id"]  = $this->recordRoutes[$parent] . '/' . $slug;
                            } else {
                                $this->recordRoutes["$contenttypeslug/$id"]  = $this->recordRoutes[$parent] . '/' . $slug;
                                $this->recordRoutes["$contenttypeslug/$slug"] = $this->recordRoutes["$contenttypeslug/$id"];
                            }
                        }
                        // Case II: Do *NOT* overwrite duplicates
                        else {
                            // Only add entry if it does not already exist.
                            if (! isset($this->recordRoutes["$contenttypeslug/$id"])) {
                                if (is_array($content)) {
                                    $this->listingRoutes["$contenttypeslug/$id"]  = $this->recordRoutes[$parent] . '/' . $slug;
                                } else {
                                    $this->recordRoutes["$contenttypeslug/$id"]  = $this->recordRoutes[$parent] . '/' . $slug;
                                    $this->recordRoutes["$contenttypeslug/$slug"] = $this->recordRoutes["$contenttypeslug/$id"];
                                }
                            }
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
     * Go from a string like 'entry/1' to a real record.
     *
     * Example:
     * - return array_map([$this, 'hydrateRecord'], $array);
     * - return $this->hydrateRecord($item);
     *
     * @param string $item
     * @return \Bolt\Legacy\Content
     */
    private function hydrateRecord($item)
    {
        if ($item) {
            return $this->storage->getContent($item);
        } else {
            return $item; // or null
        }
    }

    /**
     * @param $contenttypeslug
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

    /**
     * @return array
     */
    public function getTree()
    {
        $tree = [];

        foreach ($this->parents as $root => $parent) {
            if ($parent === null) {
                $tree[$root] = $this->makeTreeItem($root);
            }
        }

        return $tree;
    }

    /**
     * @return array
     */
    private function makeTreeItem($item) {
        $result = [];

        if (isset($this->children[$item])) {
            foreach ($this->children[$item] as $subitem) {
                $result[$subitem] = $this->makeTreeItem($subitem);
            }
        }

        return $result;
    }

    /**
     * @param string $singular_slug
     */
    public function singularToPluralContentTypeSlug($singular_slug)
    {
        $contenttypes = $this->boltConfig->get('contenttypes');
        foreach ($contenttypes as $key => $type) {
            if ($type['singular_slug'] === $singular_slug) {
                return isset($type['slug']) ? $type['slug'] : $key;
            }
        }
        return null;
    }

}
