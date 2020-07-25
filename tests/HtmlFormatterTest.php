<?php

namespace HtmlFormatter\Test;

use HtmlFormatter\HtmlFormatter;

/**
 * @group HtmlFormatter
 * @covers \HtmlFormatter\HtmlFormatter
 */
class HtmlFormatterTest extends \PHPUnit\Framework\TestCase {

	/**
	 * @dataProvider provideHtmlData
	 * @param string $input
	 * @param string $expectedText
	 * @param array $expectedRemoved
	 * @param callable|bool $callback
	 */
	public function testTransform( $input, $expectedText,
		$expectedRemoved = [], $callback = false
	) {
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
		$expectedRemoved = array_map( 'self::normalize', $expectedRemoved );

		$this->assertEquals( self::normalize( $expectedText ), self::normalize( $html ) );
		$this->assertEquals( asort( $expectedRemoved ), asort( $removed ) );
	}

	public function testInvalidSelectorsThrow() {
		$f = new HtmlFormatter( '' );
		$f->remove( 'foo[bar]' );
		$this->expectException( \Exception::class );
		$f->filterContent();
	}

	private static function normalize( $s ) {
		return str_replace( "\n", '',
			// "yay" to Windows!
			str_replace( "\r", '', $s )
		);
	}

	public static function provideHtmlData() {
		$removeImages = function ( HtmlFormatter $f ) {
			$f->setRemoveMedia();
		};
		$removeTags = function ( HtmlFormatter $f ) {
			$f->remove( [ 'table', '.foo', '#bar', 'div.baz' ] );
		};
		$flattenSomeStuff = function ( HtmlFormatter $f ) {
			$f->flatten( [ 's', 'div' ] );
		};
		$flattenEverything = function ( HtmlFormatter $f ) {
			$f->flattenAllTags();
		};
		yield 'remove images' => [
			'<img src="/foo/bar.jpg" alt="Blah"/>',
			'',
			[ '<img src="/foo/bar.jpg" alt="Blah">' ],
			$removeImages,
		];
		yield 'basic tag removal' => [
			// @codingStandardsIgnoreStart Ignore long line warnings.
			'<table><tr><td>foo</td></tr></table><div class="foo">foo</div><div class="foo quux">foo</div><span id="bar">bar</span>
<strong class="foo" id="bar">foobar</strong><div class="notfoo">test</div><div class="baz"/>
<span class="baz">baz</span>',
			// @codingStandardsIgnoreEnd
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
			'<div><s>foo</s> <span>bar</span></div>',
			'foo <span>bar</span>',
			[],
			$flattenSomeStuff,
		];
		yield 'total flattening' => [
			'<div style="foo">bar<sup>2</sup></div>',
			'bar2',
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
	}

	/**
	 * Verifies that HtmlFormatter does not needlessly parse HTML
	 */
	public function testQuickProcessing() {
		$f = $this->getMockBuilder( HtmlFormatter::class )
			->setMethods( [ 'getDoc' ] )
			->setConstructorArgs( [ 'foo' ] )
			->getMock();
		$f->expects( self::never() )
			->method( 'getDoc' );
		/** @var HtmlFormatter $f */
		$f->filterContent();
	}
}
