HtmlFormatter is a library spun off MediaWiki that allows you to load HTML into DomDocument, perform manipulations on it, and then return a HTML string.

Usage
-----

```php
use HtmlFormatter\HtmlFormatter;
// Load HTML that already has doctype and stuff
$formatter = new HtmlFormatter( $html );

// ...or one that doesn't have it
$formatter = new HtmlFormatter( HtmlFormatter::wrapHTML( $html ) );

// Add rules to remove some stuff
$formatter->remove( 'img' );
$formatter->remove( [ '.some_css_class', '#some_id', 'div.some_other_class' ] );
// Only the above syntax is supported, not full CSS/jQuery selectors

// These tags get replaced with their inner HTML,
// e.g. &lt;tag>foo&lt;/tag> --> foo
// Only tag names are supported here
$formatter->flatten( 'span' );
$formatter->flatten( [ 'code', 'pre' ] );

// Actually perform the removals
$formatter->filterContent();

// Direct DomDocument manipulations are possible
$formatter->getDoc()->createElement( 'p', 'Appended paragraph' );

// Get resulting HTML
$processedHtml = $formatter->getText();
```

License
-------
Copyright 2011-2024 MediaWiki contributors

Released under the GNU General Public License version 2, see `COPYING`.
