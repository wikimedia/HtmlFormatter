<?php

namespace HtmlFormatter\Test;

use Generator;
use HtmlFormatter\HtmlFormatter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @group HtmlFormatter
 * @covers \HtmlFormatter\HtmlFormatter
 */
class HtmlFormatterTest extends TestCase {

	/**
	 * @dataProvider provideHtmlData
	 * @param string $input
	 * @param string $expectedText
	 * @param array $expectedRemoved
	 * @param callable|bool $callback
	 */
	public function testTransform(
		string $input,
		string $expectedText,
		array $expectedRemoved = [],
		$callback = false
	): void {
		$input = self::normalize( $input );
		$formatter = new HtmlFormatter( HtmlFormatter::wrapHTML( $input ) );
		if ( $callback ) {
			$callback( $formatter );
		}
		$removedElements = $formatter->filterContent();
		$html = $formatter->getText();
		$removed = [];
		foreach ( $removedElements as $removedElement ) {
			$removed[] = self::normalize( $formatter->getText( $removedElement ) );
		}
		$expectedRemoved = array_map( [ self::class, 'normalize' ], $expectedRemoved );

		$this->assertEquals( self::normalize( $expectedText ), self::normalize( $html ) );
		$this->assertEquals( asort( $expectedRemoved ), asort( $removed ) );
	}

	public function testInvalidSelectorsThrow(): void {
		$f = new HtmlFormatter( '' );
		$f->remove( 'foo[bar]' );
		$this->expectException( InvalidArgumentException::class );
		$f->filterContent();
	}

	private static function normalize( $s ) {
		return str_replace( [ "\r", "\n" ], '', $s );
	}

	public static function provideHtmlData(): Generator {
		$removeImages = static function ( HtmlFormatter $f ) {
			$f->setRemoveMedia();
		};
		$removeTags = static function ( HtmlFormatter $f ) {
			$f->remove( [ 'table', '.foo', '#bar', 'div.baz' ] );
		};
		$flattenSomeStuff = static function ( HtmlFormatter $f ) {
			$f->flatten( [ 's', 'div' ] );
		};
		$flattenEverything = static function ( HtmlFormatter $f ) {
			$f->flattenAllTags();
		};
		$removeComments = static function ( HtmlFormatter $f ) {
			$f->setRemoveComments( true );
		};
		yield 'remove images' => [
			'<img src="/foo/bar.jpg" alt="Blah"/>',
			'',
			[ '<img src="/foo/bar.jpg" alt="Blah">' ],
			$removeImages,
		];
		yield 'basic tag removal' => [
			// phpcs:disable Generic.Files.LineLength
			'<table><tr><td>foo</td></tr></table><div class="foo">foo</div><div class="foo quux">foo</div><span id="bar">bar</span>
<strong class="foo" id="bar">foobar</strong><div class="notfoo">test</div><div class="baz"/>
<span class="baz">baz</span>',
			// phpcs:enable
			'<div class="notfoo">test</div>
<span class="baz">baz</span>',
			[
				'<table><tr><td>foo</td></tr></table>',
				'<div class="foo">foo</div>',
				'<div class="foo quux">foo</div>',
				'<span id="bar">bar</span>',
				'<strong class="foo" id="bar">foobar</strong>',
				'<div class="baz"/>',
			],
			$removeTags,
		];
		yield 'selector ".foo" should not match ".telefoon" or ".nofoo"' => [
			'<div class="telefoon">A</div><div class="nofoo">B</div><div class="foo">C</div>',
			'<div class="telefoon">A</div><div class="nofoo">B</div>',
			[],
			$removeTags,
		];
		yield 'selector ".foo" should not match ".no-foo" or ".foo-bar" (T231160)' => [
			'<div class="no-foo">A</div><div class="foo-bar">B</div><div class="foo foo-bar">C</div>',
			'<div class="no-foo">A</div><div class="foo-bar">B</div>',
			[],
			$removeTags,
		];
		yield 'do not flatten tags that start like chosen ones' => [
			'<div><s>foo</s> <span>bar</span> <!-- comment --></div>',
			'foo <span>bar</span> <!-- comment -->',
			[],
			$flattenSomeStuff,
		];
		yield 'total flattening' => [
			'<div style="foo">bar<sup>2</sup><!-- comment -->foo<!-- comment --></div>',
			'bar2foo',
			[],
			$flattenEverything,
		];
		yield 'UTF-8 preservation and security' => [
			'<span title="&quot; \' &amp;">&lt;Тест!&gt;</span> &amp;&lt;&#38;&#0038;&#x26;&#x026;',
			'<span title="&quot; \' &amp;">&lt;Тест!&gt;</span> &amp;&lt;&amp;&amp;&amp;&amp;',
			[],
			// Have some rules to trigger a DOM parse
			$removeTags,
		];
		// https://phabricator.wikimedia.org/T55086
		yield 'T55086' => [
			'Foo<sup id="cite_ref-1" class="reference"><a href="#cite_note-1">[1]</a></sup>'
				. ' <a href="/wiki/Bar" title="Bar" class="mw-redirect">Bar</a>',
			'Foo<sup id="cite_ref-1" class="reference"><a href="#cite_note-1">[1]</a></sup>'
				. ' <a href="/wiki/Bar" title="Bar" class="mw-redirect">Bar</a>',
		];
		yield 'T55086 and T348402, space before tags' => [
			'Foo <a href="/wiki/Bar" title="Bar" class="mw-redirect">Bar</a>',
			'Foo <a href="/wiki/Bar" title="Bar" class="mw-redirect">Bar</a>',
		];
		yield 'T55086 and T348402, space after tags' => [
			'Foo<sup id="cite_ref-1" class="reference"><a href="#cite_note-1">[1]</a></sup> Bar',
			'Foo<sup id="cite_ref-1" class="reference"><a href="#cite_note-1">[1]</a></sup> Bar',
		];
		yield 'removeComments only' => [
			'Foo<!--bar--><i>baz</i>',
			'Foo<i>baz</i>',
			[],
			$removeComments,
		];
	}

	/**
	 * Verifies that HtmlFormatter does not needlessly parse HTML
	 */
	public function testQuickProcessing(): void {
		$f = $this->getMockBuilder( HtmlFormatter::class )
			->onlyMethods( [ 'getDoc' ] )
			->setConstructorArgs( [ 'foo' ] )
			->getMock();
		$f->expects( self::never() )
			->method( 'getDoc' );
		/** @var HtmlFormatter $f */
		$f->filterContent();
	}

	private const EXCLUDED_ELEMENTS = [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', ];
	private const AUX_ELEMENTS = [ 'table', ];

	/**
	 * Ported and simplified from MediaWiki core's WikitextStructureTest::testTexts(); wikitext swapped for HTML.
	 * This test doesn't test for tag stripping that MediaWiki does.
	 */
	public function testT344811(): void {
		$text = <<<END
Opening text is opening.
<h2> Then comes header </h2>
Then we got more text
<h3> And more headers </h3>
<table class="wikitable">
<tbody>
<tr><th>Header table</th></tr>
<tr><td>row in table</td></tr>
<tr><td>another row in table</td></tr>
</tbody>
</table>
END;

		$matches = [];
		preg_match( '/<h[123456]>/', $text, $matches, PREG_OFFSET_CAPTURE );

		$formatter = new HtmlFormatter( substr( $text, 0, $matches[ 0 ][ 1 ] ) );
		$formatter->remove( self::EXCLUDED_ELEMENTS );
		$formatter->remove( self::AUX_ELEMENTS );
		$formatter->filterContent();
		$this->assertEquals( "Opening text is opening.", trim( $formatter->getText() ) );

		$formatter = new HtmlFormatter( $text );
		$formatter->remove( self::EXCLUDED_ELEMENTS );
		$formatter->filterContent();
		$formatter->remove( self::AUX_ELEMENTS );
		$auxiliaryElements = $formatter->filterContent();

		$this->assertEquals( "Opening text is opening.

Then we got more text",
			trim( $formatter->getText() ) );

		$auxText = [];
		foreach ( $auxiliaryElements as $auxiliaryElement ) {
			$auxText[] =
				trim( self::replaceNewline( $formatter->getText( $auxiliaryElement ) ) );
		}

		$expectedTable = <<<END
<table class="wikitable">
<tbody>
<tr><th>Header table</th></tr>
<tr><td>row in table</td></tr>
<tr><td>another row in table</td></tr>
</tbody>
</table>
END;

		$this->assertEquals(
			[ self::replaceNewline( $expectedTable ) ],
			$auxText
		);
	}

	public static function removeBeforeIncludingProvider() {
		return [
			'no match, unchanged' => [ 'example', 'no match', 'example' ],
			'simple match' => [ 'example text content', 'text', ' content' ],
			'matches last occurance' => [ 'example text text content', 'text', ' content' ],
		];
	}

	/**
	 * @dataProvider removeBeforeIncludingProvider
	 */
	public function testRemoveBeforeIncluding( $haystack, $needle, $expect ) {
		$this->assertEquals( $expect, HtmlFormatter::removeBeforeIncluding( $haystack, $needle ) );
	}

	public static function removeAfterIncludingProvider() {
		return [
			'no match, unchanged' => [ 'example', 'no match', 'example' ],
			'simple match' => [ 'example text content', 'text', 'example ' ],
			'matches first occurance' => [ 'example text text content', 'text', 'example ' ],
		];
	}

	/**
	 * @dataProvider removeAfterIncludingProvider
	 */
	public function testRemoveAfterIncluding( $haystack, $needle, $expect ) {
		$this->assertEquals( $expect, HtmlFormatter::removeAfterIncluding( $haystack, $needle ) );
	}

	public static function removeBetweenIncludingProvider() {
		return [
			'no match, unchanged' => [ 'example', 'no match', 'no match', 'example' ],
			'simple match' => [ 'example <!-- text --> content', '<!--', '-->', 'example  content' ],
			'nested open' => [ 'example [ascii [text] content', '[', ']', 'example  content' ],
			'nested close' => [ 'example [ascii] text] content', '[', ']', 'example  text] content' ],
			'multiple matches' => [ 'example [ascii] text [content]', '[', ']', 'example  text ' ],
		];
	}

	/**
	 * @dataProvider removeBetweenIncludingProvider
	 */
	public function testRemoveBetweenIncluding( $haystack, $open, $close, $expect ) {
		$this->assertEquals( $expect, HtmlFormatter::removeBetweenIncluding( $haystack, $open, $close ) );
	}

	private static function replaceNewline( string $input ): string {
		return str_replace( [ "\n", "\r" ], "", $input );
	}
}
