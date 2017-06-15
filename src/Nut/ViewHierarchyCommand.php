<?php

namespace Bolt\Extension\TwoKings\HierarchicalRoutes\Nut;

use Bolt\Nut\BaseCommand;
use PBergman\Console\Helper\TreeHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *
 */
class ViewHierarchyCommand extends BaseCommand
{
    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName('hierarchy:view')
            ->setDescription('View the generated hierarchy structure')
            ->addOption(
               'full',
               null,
               InputOption::VALUE_NONE,
               'Display full record title and link'
            )
        ;
    }

    /**
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $tree = $this->app['hierarchicalroutes.service']->getTree();

        if ($input->getOption('full')) {
            $tree = $this->updateTree($tree);
        }

        $treeHelper = new TreeHelper($output);
        $treeHelper->addArray($tree);
        $treeHelper->printTree($output);
    }

    /**
     * Rewrite keys of array with record values!
     */
    private function updateTree($tree)
    {
        $newTree = [];

        foreach ($tree as $k => $v) {
            list($newK, $newV) = $this->updateItem($k, $v);
            $newTree[$newK] = $newV;
        }

        return $newTree;
    }

    /**
     *
     */
    private function updateItem($node, $children)
    {
        $newItem = [];

        // Not sure why in `HierarchicalRoutesService`, I don't need to do: ['status' => '!'].
        $record = $this->app['storage']->getContent($node, ['status' => '!']);
        $routes = $this->app['hierarchicalroutes.service']->getRecordRoutes();
        $link   = isset($routes[$node]) ? $routes[$node] : '';

        $title = '';
        if ($record === false) {
            // I believe this can not happen, but just in case
            $title = "<error>Record not found!</error>";
        } elseif (is_array($record)) {
            $title = "<fg=yellow>[ ... ]</>";
        }

        else {
            $title = $record->getTitle();
            $title = "<fg=yellow>$title</>";
            switch ( $record['status'] ) {
                case 'published':
                    $title = "[<fg=green;options=bold>✔</>] $title";
                    break;
                case 'draft':
                case 'timed':
                    $title = "[<fg=blue;options=bold>~</>] $title";
                    break;
                case 'held':
                    $title = "[<fg=red;options=bold>✘</>] $title";
                    break;
            }
        }

        $newKey = sprintf(
            "[<fg=green>%s</>] %s <fg=red>/%s</>",
            $node,
            $title,
            $link
        );

        // Note: $record->link() does not work

        $arr = [];
        foreach ($children as $k => $v) {
            list($newK, $newV) = $this->updateItem($k, $v);
            $arr[$newK] = $newV;
        }

        return [
            $newKey,
            $arr
        ];
    }
}
