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
    /** @var string[] $colors */
    private $colors = [
        'published' => 'green',
        'timed'     => 'yellow',
        'draft'     => 'blue',
        'held'      => 'red',
    ];

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

        if ($input->getOption('full')) {
            foreach ($this->colors as $status => $color) {
                $output->writeln(
                    sprintf("[<fg=%s;options=bold>●</>] %s", $color, $status)
                );
            }
        }
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

        $newKey = '';
        if ($record === false) {
            // I believe this can not happen, but just in case
            $newKey = sprintf(
                "[<fg=red>%s</>] [<fg=red;options=bold>●</>] <error>Record not found!</error> <fg=red>%s</>",
                $node,
                $link
            );
        }
        elseif (is_array($record)) {
            $newKey = sprintf(
                "[%s] [<options=bold>●</>] [ ... ] %s",
                $node,
                $link
            );
        }
        else {
            $color = $this->colors[ $record['status'] ];
            $newKey = sprintf(
                '[<fg=%s>%s</>] [<fg=%s;options=bold>●</>] %s <fg=red>%s</>',
                $color,
                $node,
                $color,
                $record->getTitle(),
                $link
            );
        }

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
