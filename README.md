# Resource Library

A filterable library of downloadable documents for WordPress: publications, reports, guides, policy papers, datasets. One plugin instead of a custom post type plugin, a custom fields plugin, a filter plugin and a search plugin fighting each other.

Search, filter by type, subject and year, paginate, download. All over AJAX, no page reloads.

## Why it exists

This pattern gets rebuilt badly on a lot of sites. The usual approach stacks four plugins: one to register the post type, one for the custom fields, one for the front end filter, one for search. They each own a piece of the query, none of them own the whole thing, and the result breaks whenever a page builder updates or post IDs shift.

This does all four in one plugin that owns its own query.

## Types and subjects are taxonomies

The important design decision. Every organisation that needs a resource library needs different vocabulary. A law firm wants *Case Note* and *Employment*. A university wants *Thesis* and *Microbiology*. A council wants *Consultation* and *Planning*.

So there is no hardcoded list. **Resource Type** and **Subject** are real taxonomies. You add your own terms in the admin the same way you add categories, and the filter dropdowns populate from the terms that actually have resources behind them.

That also means WordPress gives you the whole editing interface for free, and the terms are queryable, archivable and REST exposed like any other taxonomy.

## Usage

Put this where you want the library:

```
[resource_library]
```

Options:

```
[resource_library per_page="24" columns="4"]
```

For view and download buttons inside a single resource template or a page builder loop:

```
[resource_buttons]
```

## What you get

- **Resources** post type with archive, editor, excerpt and featured image
- **Resource Types** and **Subjects** taxonomies
- A file field and a year field on each resource
- AJAX search with a 400ms debounce, so typing does not fire a request per keystroke
- In flight requests are aborted when a newer one starts, so results cannot arrive out of order
- Pagination that collapses to first, last and a window around the current page
- Year dropdown built from published resources only, cached for an hour and cleared on save

## Notes on the implementation

**No jQuery.** The filter UI is small enough that the dependency would be the largest thing on the page. Plain `fetch` and `AbortController`.

**Cards are built with `createElement`, not HTML strings.** Titles and excerpts come back from the server as text and are set with `textContent`, so a resource titled with markup cannot inject it into the page.

**Assets load only where the shortcode runs.** Registered on `wp_enqueue_scripts`, enqueued inside the shortcode. A site with one resources page does not ship this CSS on every request.

**Scoped CSS, no `!important`.** Everything sits under `.reslib` and is driven by custom properties, so a theme can restyle it without a specificity war:

```css
.reslib {
    --reslib-columns: 4;
    --reslib-radius: 0;
    --reslib-accent: #0f6e56;
}
```

## Filters

| Filter | Purpose |
|---|---|
| `reslib_post_type_args` | Change the post type registration |
| `reslib_type_taxonomy_args` | Change the type taxonomy |
| `reslib_subject_taxonomy_args` | Change the subject taxonomy |
| `reslib_query_args` | Modify the query behind the filter results |

## Requirements

WordPress 6.0+, PHP 7.4+. No other plugins required. Works with Elementor, which is opted in automatically when Elementor is active.

## Status

Extracted from a production site and generalised for release. The original stored type and subject as post meta with a hardcoded option list; the taxonomy model here replaced that so the plugin is usable by anyone.

Issues and pull requests welcome.

## Licence

GPL-2.0-or-later.
