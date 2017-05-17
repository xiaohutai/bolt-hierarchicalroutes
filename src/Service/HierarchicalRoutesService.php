<?php

namespace Bolt\Extension\TwoKings\HierarchicalRoutes\Service;

use Bolt\Extension\TwoKings\HierarchicalRoutes\Config\Config;
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

    /** @var Application $app */
    private $app;
    // todo: temporary, search for `$this->app` when refactoring
    //
    // So far:
    //      - $this->app['menu']
    //      - $this->app['storage']
    //      - $this->app['query']
    //
    // also want:
    //      - $this->app['logger.system']
    //      - $this->app['cache']

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
     * @param Config      $config
     * @param Application $app
     */
    public function __construct(Config $config, Application $app)
    {
        $this->config = $config;
        $this->app    = $app;

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
            $this->app['logger.system']->info('Ignoring cache. Rebuilding hierarchical data.', ['event' => 'extension']);
        } else {
            $this->app['logger.system']->info('Cache expired or not found. Rebuilding hierarchical data.', ['event' => 'extension']);
        }

        $menu = $this->config->get('menu');
        if (is_array($menu)) {
            foreach ($menu as $menuName) {
                $this->importMenu($menuName);
            }
        } else {
            $this->importMenu($menu);
        }

        $this->importRules($this->config->get('rules'));

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
            $this->$property = $this->app['cache']->fetch($this->cachePrefix . $property);

            if ($this->$property === false) {
                // Reset all properties and import
                foreach ($this->properties as $prop) {
                    $this->$prop = [];
                }
                return false;
            }
        }

        // This will flood the logger system.
        // $this->app['logger.system']->info('Using cached data', ['event' => 'extension']);

        return true;
    }

    /**
     * Stores all data to cache.
     */
    private function toCache()
    {
        foreach ($this->properties as $property) {
            $this->app['cache']->save(
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
        /** @var \Bolt\Menu\Menu $menu */
        // $menu = $this->app['menu']->menu($identifier);
        // $menu = $menu->getItems();

        $menu = $this->app['config']->get('menu/' . $identifier, []);

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
            $content = $this->app['storage']->getContent($item['path'], ['hydrate' => false]);
        }

        // Only items with records can be in a hierarchy, otherwise it doesn't make much sense
        if ($content && !is_array($content)) {
            $id          = $content->id;
            $slug        = $content->values['slug'];
            $contenttype = $content->contenttype['slug']; // $content->contenttype['singular_slug'];

            $this->parents["$contenttype/$id"] = $parent;
            $this->slugs["$contenttype/$id"]   = $slug;
            $this->recordRoutes["$contenttype/$id"]  = $parent ? $this->recordRoutes[$parent] . '/' . $slug : $slug;

            if ($parent) {
                $this->children[$parent][] = "$contenttype/$id"; // but also add links and other items ???
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

            $this->parents[$path] = $parent;
            $this->slugs[$path]   = $path;
            // $this->recordRoutes[$path]  = $parent ? $this->recordRoutes[$parent] . '/' . $path : $path;
            $this->listingRoutes[$path]  = $parent ? $this->recordRoutes[$parent] . '/' . $path : $path;

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
        $content = $this->app['storage']->getContent($params['parent'], ['hydrate' => false]);

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
                    $items = $this->app['query']->getContent($params['query'], $params['parameters']);
                    foreach ($items as $item) {
                        $contenttypeslug = $item->getContenttype()['slug'];
                        $id = $item->getId();
                        $slug = $item->getSlug();

                        $this->parents["$contenttypeslug/$id"] = $parent;
                        $this->children[$parent][]             = "$contenttypeslug/$id";
                        $this->slugs["$contenttypeslug/$id"]   = $slug;

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
        return isset($this->parents["$contenttypeslug/$id"]) ? $this->parents["$contenttypeslug/$id"] : null;
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

        return $parents;
    }

    /**
     * Returns an array of all the children of the current record.
     */
    public function getChildren($record)
    {
        $contenttypeslug = $record->contenttype['slug'];
        $id = $record->id;

        if (isset($this->children["$contenttypeslug/$id"])) {
            return $this->children["$contenttypeslug/$id"];
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

        return array_values($siblings);
    }

    public function getParentLinkForContentType($contenttypeslug)
    {
        foreach ($this->contenttypeRules as $parent => $contenttypes) {
            if (in_array($contenttypeslug, $contenttypes)) {
                return $this->recordRoutes[$parent];
            }
        }

        return null;
    }

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
