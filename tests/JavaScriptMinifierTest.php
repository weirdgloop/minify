<?php
use Wikimedia\Minify\JavaScriptMinifier;

/**
 * @covers Wikimedia\Minify\JavaScriptMinifier
 * @coversDefaultClass Wikimedia\Minify\JavaScriptMinifier
 */
class JavaScriptMinifierTest extends PHPUnit\Framework\TestCase {

	protected function tearDown() : void {
		// Reset
		$this->setMaxLineLength( 1000 );
		parent::tearDown();
	}

	private function setMaxLineLength( $val ) {
		$classReflect = new ReflectionClass( JavaScriptMinifier::class );
		$propertyReflect = $classReflect->getProperty( 'maxLineLength' );
		$propertyReflect->setAccessible( true );
		$propertyReflect->setValue( JavaScriptMinifier::class, $val );
	}

	public static function provideCases() {
		return [

			// Basic whitespace and comments that should be stripped entirely
			[ "\r\t\f \v\n\r", "" ],
			[ "/* Foo *\n*bar\n*/", "" ],

			/**
			 * Slashes used inside block comments (T28931).
			 * At some point there was a bug that caused this comment to be ended at '* /',
			 * causing /M... to be left as the beginning of a regex.
			 */
			[
				"/**\n * Foo\n * {\n * 'bar' : {\n * "
					. "//Multiple rules with configurable operators\n * 'baz' : false\n * }\n */",
				"" ],

			/**
			 * '  Foo \' bar \
			 *  baz \' quox '  .
			 */
			[
				"'  Foo  \\'  bar  \\\n  baz  \\'  quox  '  .length",
				"'  Foo  \\'  bar  \\\n  baz  \\'  quox  '.length"
			],
			[
				"\"  Foo  \\\"  bar  \\\n  baz  \\\"  quox  \"  .length",
				"\"  Foo  \\\"  bar  \\\n  baz  \\\"  quox  \".length"
			],
			[ "// Foo b/ar baz", "" ],
			[
				"/  Foo  \\/  bar  [  /  \\]  /  ]  baz  /  .length",
				"/  Foo  \\/  bar  [  /  \\]  /  ]  baz  /.length"
			],

			// HTML comments
			[ "<!-- Foo bar", "" ],
			[ "<!-- Foo --> bar", "" ],
			[ "--> Foo", "" ],
			[ "x --> y", "x-->y" ],

			// Semicolon insertion
			[ "(function(){return\nx;})", "(function(){return\nx;})" ],
			[ "throw\nx;", "throw\nx;" ],
			[ "throw new\nError('x');", "throw new Error('x');" ],
			[ "while(p){continue\nx;}", "while(p){continue\nx;}" ],
			[ "while(p){break\nx;}", "while(p){break\nx;}" ],
			[ "var\nx;", "var x;" ],
			[ "x\ny;", "x\ny;" ],
			[ "x\n++y;", "x\n++y;" ],
			[ "x\n!y;", "x\n!y;" ],
			[ "x\n{y}", "x\n{y}" ],
			[ "x\n+y;", "x+y;" ],
			[ "x\n(y);", "x(y);" ],
			[ "5.\nx;", "5.\nx;" ],
			[ "0xFF.\nx;", "0xFF.x;" ],
			[ "5.3.\nx;", "5.3.x;" ],

			// Cover failure case for incomplete hex literal
			[ "0x;", false ],

			// Cover failure case for number with no digits after E
			[ "1.4E", false ],

			// Cover failure case for number with several E
			[ "1.4EE2", false ],
			[ "1.4EE", false ],

			// Cover failure case for number with several E (nonconsecutive)
			// FIXME: This is invalid, but currently tolerated
			[ "1.4E2E3", "1.4E2 E3" ],

			// Semicolon insertion between an expression having an inline
			// comment after it, and a statement on the next line (T29046).
			[
				"var a = this //foo bar \n for ( b = 0; c < d; b++ ) {}",
				"var a=this\nfor(b=0;c<d;b++){}"
			],

			// Cover failure case of incomplete regexp at end of file (T75556)
			// FIXME: This is invalid, but currently tolerated
			[ "*/", "*/" ],

			// Cover failure case of incomplete char class in regexp (T75556)
			// FIXME: This is invalid, but currently tolerated
			[ "/a[b/.test", "/a[b/.test" ],

			// Cover failure case of incomplete string at end of file (T75556)
			// FIXME: This is invalid, but currently tolerated
			[ "'a", "'a" ],

			// Token separation
			[ "x  in  y", "x in y" ],
			[ "/x/g  in  y", "/x/g in y" ],
			[ "x  in  30", "x in 30" ],
			[ "x  +  ++  y", "x+ ++y" ],
			[ "x ++  +  y", "x++ +y" ],
			[ "x  /  /y/.exec(z)", "x/ /y/.exec(z)" ],

			// State machine
			[ "/  x/g", "/  x/g" ],
			[ "(function(){return/  x/g})", "(function(){return/  x/g})" ],
			[ "+/  x/g", "+/  x/g" ],
			[ "++/  x/g", "++/  x/g" ],
			[ "x/  x/g", "x/x/g" ],
			[ "(/  x/g)", "(/  x/g)" ],
			[ "if(/  x/g);", "if(/  x/g);" ],
			[ "(x/  x/g)", "(x/x/g)" ],
			[ "([/  x/g])", "([/  x/g])" ],
			[ "+x/  x/g", "+x/x/g" ],
			[ "{}/  x/g", "{}/  x/g" ],
			[ "+{}/  x/g", "+{}/x/g" ],
			[ "(x)/  x/g", "(x)/x/g" ],
			[ "if(x)/  x/g", "if(x)/  x/g" ],
			[ "for(x;x;{}/  x/g);", "for(x;x;{}/x/g);" ],
			[ "x;x;{}/  x/g", "x;x;{}/  x/g" ],
			[ "x:{}/  x/g", "x:{}/  x/g" ],
			[ "switch(x){case y?z:{}/  x/g:{}/  x/g;}", "switch(x){case y?z:{}/x/g:{}/  x/g;}" ],
			[ "function x(){}/  x/g", "function x(){}/  x/g" ],
			[ "+function x(){}/  x/g", "+function x(){}/x/g" ],

			// Multiline quoted string
			[ "var foo=\"\\\nblah\\\n\";", "var foo=\"\\\nblah\\\n\";" ],

			// Multiline quoted string followed by string with spaces
			[
				"var foo=\"\\\nblah\\\n\";\nvar baz = \" foo \";\n",
				"var foo=\"\\\nblah\\\n\";var baz=\" foo \";"
			],

			// URL in quoted string ( // is not a comment)
			[
				"aNode.setAttribute('href','http://foo.bar.org/baz');",
				"aNode.setAttribute('href','http://foo.bar.org/baz');"
			],

			// URL in quoted string after multiline quoted string
			[
				"var foo=\"\\\nblah\\\n\";\naNode.setAttribute('href','http://foo.bar.org/baz');",
				"var foo=\"\\\nblah\\\n\";aNode.setAttribute('href','http://foo.bar.org/baz');"
			],

			// Division vs. regex nastiness
			[
				"alert( (10+10) / '/'.charCodeAt( 0 ) + '//' );",
				"alert((10+10)/'/'.charCodeAt(0)+'//');"
			],
			[ "if(1)/a /g.exec('Pa ss');", "if(1)/a /g.exec('Pa ss');" ],

			// Unicode letter characters should pass through ok in identifiers (T33187)
			[ "var KaŝSkatolVal = {}", 'var KaŝSkatolVal={}' ],

			// Per spec unicode char escape values should work in identifiers,
			// as long as it's a valid char. In future it might get normalized.
			[ "var Ka\\u015dSkatolVal = {}", 'var Ka\\u015dSkatolVal={}' ],

			// Some structures that might look invalid at first sight
			[ "var a = 5.;", "var a=5.;" ],
			[ "5.0.toString();", "5.0.toString();" ],
			[ "5..toString();", "5..toString();" ],
			// Cover failure case for too many decimal points
			[ "5...toString();", false ],
			[ "5.\n.toString();", '5..toString();' ],

			// Boolean minification (!0 / !1)
			[ "var a = { b: true };", "var a={b:!0};" ],
			[ "var a = { true: 12 };", "var a={true:12};" ],
			[ "a.true = 12;", "a.true=12;" ],
			[ "a.foo = true;", "a.foo=!0;" ],
			[ "a.foo = false;", "a.foo=!1;" ],
			[ "a.foo = bar ? false : true;", "a.foo=bar?!1:!0;" ],
			[ "func( true, false )", "func(!0,!1)" ],
			[ "function f() { return false; }", "function f(){return!1;}" ],
			[ "let f = () => false;", "let f=()=>!1;" ],

			// Template strings
			[ 'let a = `foo + ${ 1 + 2 } + bar`;', 'let a=`foo + ${1+2} + bar`;' ],
			[ 'let a = `foo + ${ "hello world" } + bar`;', 'let a=`foo + ${"hello world"} + bar`;' ],
			[
				'let a = `foo + ${ `bar + ${ `baz + ${ `quux` } + lol` } + ${ `yikes` } ` }`, b = 3;',
				'let a=`foo + ${`bar + ${`baz + ${`quux`} + lol`} + ${`yikes`} `}`,b=3;'
			],
			[ 'let a = `foo$\\` + 23;', 'let a=`foo$\\`+23;' ],

			// Behavior of 'yield' in generator functions vs normal functions
			[ "function *f( x ) {\n if ( x )\n yield\n ( 42 )\n}", "function*f(x){if(x)yield\n(42)}" ],
			[ "function g( y ) {\n const yield = 42\n yield\n ( 42 )\n}", "function g(y){const yield=42\nyield(42)}" ],
			// Normal function nested inside generator function
			[
				<<<JAVASCRIPT
				function *f( x ) {
					if ( x )
						yield
						( 42 )
					function g() {
						const yield = 42
						yield
						( 42 )
						return
						42
					}
					yield
					42
				}
JAVASCRIPT
				,
				"function*f(x){if(x)yield\n(42)\nfunction g(){const yield=42\nyield(42)\nreturn\n42}yield\n42}",
			],

			// Object literals: optional values, computed keys
			[ "let a = { foo, bar: 'baz', [21 * 2]: 'answer' }", "let a={foo,bar:'baz',[21*2]:'answer'}" ],
			[
				"let a = { [( function ( x ) {\n if ( x )\nreturn\nx*2 } ( 21 ) )]: 'wrongAnswer' }",
				"let a={[(function(x){if(x)return\nx*2}(21))]:'wrongAnswer'}"
			],
			// Functions in object literals
			[
				"let a = { foo() { if ( x )\n return\n 42 }, bar: 21 * 2 };",
				"let a={foo(){if(x)return\n42},bar:21*2};"
			],
			[
				"let a = { *f() { yield\n(42); }, g() { let yield = 42; yield\n(42); };",
				"let a={*f(){yield\n(42);},g(){let yield=42;yield(42);};"
			],
			[
				"function *f() { return { g() { let yield = 42; yield\n(42); } }; }",
				"function*f(){return{g(){let yield=42;yield(42);}};}"
			],
			[
				"function *f() { return { *h() { yield\n(42); } }; }",
				"function*f(){return{*h(){yield\n(42);}};}"
			],

			// Classes
			[
				"class Foo { *f() { yield\n(42); }, g() { let yield = 42; yield\n(42); } }",
				"class Foo{*f(){yield\n(42);},g(){let yield=42;yield(42);}}"
			],
			[
				"class Foo { static *f() { yield\n(42); }, static g() { let yield = 42; yield\n(42); } }",
				"class Foo{static*f(){yield\n(42);},static g(){let yield=42;yield(42);}}"
			],
			[
				"class Foo { get bar() { return\n42 } set baz( val ) { throw new Error( 'yikes' ) } }",
				"class Foo{get bar(){return\n42}set baz(val){throw new Error('yikes')}}"
			],
			// Extends
			[ "class Foo extends Bar { f() { return\n42 } }", "class Foo extends Bar{f(){return\n42}}" ],
			[ "class Foo extends Bar.Baz { f() { return\n42 } }", "class Foo extends Bar.Baz{f(){return\n42}}" ],
			[
				"class Foo extends (function (x) { return\n x.Baz; }(Bar)) { f() { return\n42 } }",
				"class Foo extends(function(x){return\nx.Baz;}(Bar)){f(){return\n42}}"
			],
			[
				"class Foo extends function(x) {return\n 42} { *f() { yield\n 42 } }",
				"class Foo extends function(x){return\n42}{*f(){yield\n42}}"
			],

			// Arrow functions
			[ "let a = ( x, y ) => x + y;", "let a=(x,y)=>x+y;" ],
			[ "let a = ( x, y ) => { return \n x + y };", "let a=(x,y)=>{return\nx+y};" ],
			[ "let a = ( x, y ) => { return x + y; }\n( 1, 2 )", "let a=(x,y)=>{return x+y;}\n(1,2)" ],
			[ "let a = ( x, y ) => { return x + y; }\n+5", "let a=(x,y)=>{return x+y;}\n+5" ],
			// Note that non-arrow functions behave differently:
			[ "let a = function ( x, y ) { return x + y; }\n( 1, 2 )", "let a=function(x,y){return x+y;}(1,2)" ],
			[ "let a = function ( x, y ) { return x + y; }\n+5", "let a=function(x,y){return x+y;}+5" ],

			// export
			[ "export { Foo, Bar as Baz } from 'thingy';", "export{Foo,Bar as Baz}from'thingy';" ],
			[ "export * from 'thingy';", "export*from'thingy';" ],
			[ "export class Foo { f() { return\n 42 } }", "export class Foo{f(){return\n42}}" ],
			[ "export default class Foo { *f() { yield\n 42 } }", "export default class Foo{*f(){yield\n42}}" ],
			// import
			[ "import { Foo, Bar as Baz, Quux } from 'thingy';", "import{Foo,Bar as Baz,Quux}from'thingy';" ],
			[ "import * as Foo from 'thingy';", "import*as Foo from'thingy';" ],
			[ "import Foo, * as Bar from 'thingy';", "import Foo,*as Bar from'thingy';" ],
			// Semicolon insertion before import/export
			[ "( x, y ) => { return x + y; }\nexport class Foo {}", "(x,y)=>{return x+y;}\nexport class Foo{}" ],
			[ "let x = y + 3\nimport Foo from 'thingy';", "let x=y+3\nimport Foo from'thingy';" ],
		];
	}

	/**
	 * @dataProvider provideCases
	 */
	public function testMinifyOutput( $code, $expectedOutput ) {
		$minified = JavaScriptMinifier::minify( $code );

		$this->assertEquals(
			$expectedOutput,
			$minified,
			"Minified output should be in the form expected."
		);
	}

	public static function provideLineBreaker() {
		return [
			[
				// Regression tests for T34548.
				// Must not break between 'E' and '+'.
				'var name = 1.23456789E55;',
				[
					'var',
					'name',
					'=',
					'1.23456789E55',
					';',
				],
			],
			[
				'var name = 1.23456789E+5;',
				[
					'var',
					'name',
					'=',
					'1.23456789E+5',
					';',
				],
			],
			[
				'var name = 1.23456789E-5;',
				[
					'var',
					'name',
					'=',
					'1.23456789E-5',
					';',
				],
			],
			[
				// Must not break before '++'
				'if(x++);',
				[
					'if',
					'(',
					'x++',
					')',
					';',
				],
			],
			[
				// Regression test for T201606.
				// Must not break between 'return' and Expression.
				// Was caused by bad state after '{}' in property value.
				<<<JAVASCRIPT
			call( function () {
				try {
				} catch (e) {
					obj = {
						key: 1 ? 0 : {}
					};
				}
				return name === 'input';
			} );
JAVASCRIPT
				,
				[
					'call',
					'(',
					'function',
					'(',
					')',
					'{',
					'try',
					'{',
					'}',
					'catch',
					'(',
					'e',
					')',
					'{',
					'obj',
					'=',
					'{',
					'key',
					':',
					'1',
					'?',
					'0',
					':',
					'{',
					'}',
					'}',
					';',
					'}',
					// The return Statement:
					//     return [no LineTerminator here] Expression
					'return name',
					'===',
					"'input'",
					';',
					'}',
					')',
					';',
				]
			],
			[
				// Regression test for T201606.
				// Must not break between 'return' and Expression.
				// Was caused by bad state after a ternary in the expression value
				// for a key in an object literal.
				<<<JAVASCRIPT
call( {
	key: 1 ? 0 : function () {
		return this;
	}
} );
JAVASCRIPT
				,
				[
					'call',
					'(',
					'{',
					'key',
					':',
					'1',
					'?',
					'0',
					':',
					'function',
					'(',
					')',
					'{',
					'return this',
					';',
					'}',
					'}',
					')',
					';',
				]
			],
			[
				// No newline after throw, but a newline after "throw new" is OK
				'throw new Error( "yikes" ); function f () { return ++x; }',
				[
					'throw new',
					'Error',
					'(',
					'"yikes"',
					')',
					';',
					'function',
					'f',
					'(',
					')',
					'{',
					'return++',
					'x',
					';',
					'}',
				]
			],
			[
				// Yield statement in generator function
				<<<JAVASCRIPT
				function *f( x ) {
					yield 42
					function g() {
						let yield = 42;
						yield( 42 )
						return 42
					}
					yield *21*2
				}
JAVASCRIPT
				,
				[
					'function',
					'*',
					'f',
					'(',
					'x',
					')',
					'{',
					'yield 42',
					'function',
					'g',
					'(',
					')',
					'{',
					'let',
					'yield',
					'=',
					'42',
					';',
					'yield',
					'(',
					'42',
					')',
					'return 42',
					'}',
					'yield*',
					'21',
					'*',
					'2',
					'}',
				]
			],
			[
				// Template string literal with a function body inside
				'let a = `foo + ${ ( function ( x ) { return x * 2; }( 21 ) ) } + bar`;',
				[
					'let',
					'a',
					'=',
					'`foo + ${',
					'(',
					'function',
					'(',
					'x',
					')',
					'{',
					'return x',
					'*',
					'2',
					';',
					'}',
					'(',
					'21',
					')',
					')',
					'} + bar`',
					';'
				]
			],
			[
				// Functions in classes
				"class Foo { static *f() { yield(42); }, static g() { let yield = 42; yield(42); } }",
				[
					'class',
					'Foo',
					'{',
					'static',
					'*',
					'f',
					'(',
					')',
					'{',
					'yield(',
					'42',
					')',
					';',
					'}',
					',',
					'static',
					'g',
					'(',
					')',
					'{',
					'let',
					'yield',
					'=',
					'42',
					';',
					'yield',
					'(',
					'42',
					')',
					';',
					'}',
					'}'
				]
			],
			[
				"class Foo { get bar() { return 42 } set baz( val ) { throw new Error( 'yikes' ) } }",
				[
					'class',
					'Foo',
					'{',
					'get',
					'bar',
					'(',
					')',
					'{',
					'return 42',
					'}',
					'set',
					'baz',
					'(',
					'val',
					')',
					'{',
					'throw new',
					'Error',
					'(',
					"'yikes'",
					')',
					'}',
					'}',
				]
			],
			[
				// Don't break before an arrow
				"let a = (x, y) => x + y;",
				[
					'let',
					'a',
					'=',
					'(',
					'x',
					',',
					'y',
					')=>',
					'x',
					'+',
					'y',
					';'
				]
			],
			[
				"let a = (x, y) => { return x + y; };",
				[
					'let',
					'a',
					'=',
					'(',
					'x',
					',',
					'y',
					')=>',
					'{',
					'return x',
					'+',
					'y',
					';',
					'}',
					';'
				]
			],
			[
				"export default class Foo { *f() { yield 42; } }",
				[
					'export',
					'default',
					'class',
					'Foo',
					'{',
					'*',
					'f',
					'(',
					')',
					'{',
					'yield 42',
					';',
					'}',
					'}',
				]
					],
			[
				"export { Foo, Bar as Baz, Quux };",
				[
					'export',
					'{',
					'Foo',
					',',
					'Bar',
					'as',
					'Baz',
					',',
					'Quux',
					'}',
					';'
				]
			],
			[
				"import * as Foo from 'thingy';",
				[
					'import',
					'*',
					'as',
					'Foo',
					'from',
					"'thingy'",
					';'
				]
			],
			[
				"import Foo, * as Bar from 'thingy';",
				[
					'import',
					'Foo',
					',',
					'*',
					'as',
					'Bar',
					'from',
					"'thingy'",
					';'
				]
			]
		];
	}

	/**
	 * @dataProvider provideLineBreaker
	 */
	public function testLineBreaker( $code, array $expectedLines ) {
		$this->setMaxLineLength( 1 );
		$actual = JavaScriptMinifier::minify( $code );
		$this->assertEquals(
			array_merge( [ '' ], $expectedLines ),
			explode( "\n", $actual )
		);
	}
}
