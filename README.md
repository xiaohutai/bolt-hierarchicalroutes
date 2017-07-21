# Hierarchical Routes

A simple way to get hierarchical content to work by using `menu.yml`.
No assumptions or crazy features. This extensions allows you to do a few things:

- Use one (or more) menu(s) to generate a hierarchical structure of nodes.
- Query a set of items and put them under any defined node.
- Put all instances of a specific contenttype under any defined node.
- Mount a `contentlisting` page under any defined node.

Make sure to use `path: {contenttypeslug}/{id}` convention as much as possible.
Use the [Menu Editor](https://github.com/bacbos/bolt-menu-editor) extension to
edit your extension visually. Menu items that are only defined as `link:` are
ignored.

Make sure a record exists only once in the menu tree. There is no guarantee that
this extension will behave as intended if you don't. Currently, there is a
setting `overwrite-duplicates` that allows you to always or never overwrite
existing items.


## Configuration

- `menu`: select which menu to check for as the hierarchical tree
- `rules`: apply some sets of content under a (parent) node
  - `type: contenttype`: put all items from a specific contenttype under a node
  - `type: query`: use a `setcontent`-like query to put a whole set under a node
- `settings`: other settings
  - `overwrite-duplicates`: allow duplicates to be overwritten, or not
  - `override-slugs`: allow slugs to be overridden via `menu.yml`


## Cache

Once a menu is built, it will be stored in the configuration folder, by default
in `app/config/extensions/hierarchicalroutes/`. This is invalidated and rebuilt
on every save of a record, or after updating one of the main config files
(`menu.yml` and `hierarchicalroutes.twokings.yml`).

Make sure that this folder is writeable.


## Override Slugs

If `override-slugs` is enabled, then slugs can be overridden in `menu.yml`. Just
add an additional key named `override` with the desired slug as the value.

```
    - label: Entry 1
      path: entry/1
      override: my-custom-slug
```

This allows you to re-use duplicate slugs as long as they are under different
parents, like so:

- `/project/foo/case-study`
- `/project/bar/case-study`
- `/project/baz/case-study`


## Twig functions

Note: These functions do not work for `contenttype` rules or `contentlisting` pages.

- `getParent(record)` - Returns the parent of the current record, otherwise `null`.
- `getParents(record)` - Returns an array of all the parents of the current record. This is useful for breadcrumbs: iterate over `getParents(record)|reverse`.
- `getChildren(record)` - Returns an array of all the children of the current record.
- `getSiblings(record)` - Returns an array of all the siblings of the current record.


## Nut Commands

Several Nut commands are added for CLI convenience:

- `app/nut hierarchy:build` - Build the structure tree
- `app/nut hierarchy:view` - View the structure tree (minimal)
- `app/nut hierarchy:view --full` - View the structure tree with titles and links


## Dev Notes

### Routing

This extensions works only if the default route `contentlink` would be used for
records. This extension will then attempt to re-write it, if there are any
parents for that record.


### On Handling duplicates

There is an option for overwriting existing items. However, that still does not
make a lot of sense. What should happen to its children? Perhaps an option is to
add a duplicate node, but that would make it a bit complexer.


### On Handling Circular References

I am not sure if this can happen though. If it does, it will most likely be
related to duplicate items, i.e. multiple entries of a record in the menu(s).


### On Refactoring

I'm currently using both `'storage'` and `'query'` for `getContent`:

- `$app['storage']->getContent` => `\Bolt\Legacy\Content` (_to be deprecated_)
- `$app['query']->getContent`   => `\Bolt\Storage\Entity\Content`

There's a potential bug with `query` depending on your definitions inside
`contenttypes.yml`. See [Bolt Issue 6691](https://github.com/bolt/bolt/issues/6691).


### On mounting Taxonomy pages on a Node

Would you really want this? There's a potential choice for:

  - `/foo/bar/{taxonomytype}/{slug}`
  - `/foo/bar/{slug}`

However, a route like `/foo/bar/pages/{taxonomytype}/{slug}` would not _work_,
unless that `taxonomytype` would only be bound to that one contenttype; `pages`
in this case.


## References

- https://github.com/bolt/bolt/issues/1295
- https://github.com/bolt/bolt/issues/5989
- https://github.com/bolt/bolt-extension-starter-extended
- https://github.com/bacbos/bolt-menu-editor
- https://github.com/europeana/Europeana-Professional/blob/master/extensions/local/europeana/structure-tree/Extension.php
- https://github.com/AnimalDesign/bolt-translate/blob/master/src/Routing/LocalizedUrlGenerator.php

This package makes use of:
- https://github.com/pbergman/tree-printer for generating trees on the console
