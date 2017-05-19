# Hierarchical Routes

> Heh... the term "Hierarchical Routes" actually makes no sense.

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
this extension will behave as intended if you don't.

## Configuration

- `menu`: select which menu to check for as the hierarchical tree
- `rules`: apply some sets of content under a (parent) node
  - 'type: contenttype': put all items from a specific contenttype under a node.
  - 'type: query': use a `setcontent`-like query to put a whole set under a node.
- `cache`: enable caching


## Cache

By default, caching is enabled. The internal hierarchy will be rebuilt on every
record's save and delete event. However, this is not done after saving config
files (`menu.yml` and `hierarchicalroutes.twokings.yml`), so clear the cache
when editing these, or wait until the cache expires.


## Twig functions

- `getParent(record)` - Returns the parent of the current record, otherwise `null`.
- `getParents(record)` - Returns an array of all the parents of the current record. This is useful for breadcrumbs: iterate over `getParents(record)|reverse`.
- `getChildren(record)` - Returns an array of all the children of the current record.
- `getSiblings(record)` - Returns an array of all the siblings of the current record.


## Dev Notes

### On Handling duplicates

Currently earlier data will be overwritten by later data, but that does not
necessarily make much sense. Then again, would we want to protect against every
possible case.


### On Handling Circular References

I am not sure if this can happen though. If it does, it will most likely be
related to duplicate items, i.e. multiple entries of a record in the menu(s).


### On Performance

Still not sure what will happen if rebuilding on every save will slow things
down, when dealing with enormous amount of records.


### On Refactoring

I'm currently using both `'storage'` and `'query'` for `getContent`:

- `$app['storage']->getContent` => `\Bolt\Legacy\Content` (_to be deprecated_)
- `$app['query']->getContent`   => `\Bolt\Storage\Entity\Content`


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
