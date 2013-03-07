<?php

class Scribunto_LuaTitleLibraryTests extends Scribunto_LuaEngineTestBase {
	protected static $moduleName = 'TitleLibraryTests';

	public static function suite( $className ) {
		global $wgInterwikiCache;
		if ( $wgInterwikiCache ) {
			$suite = new PHPUnit_Framework_TestSuite;
			$suite->setName( $className );
			$suite->addTest(
				new Scribunto_LuaEngineTestSkip(
					$className, 'Cannot run TitleLibrary tests when $wgInterwikiCache is set'
				), array( 'Lua' )
			);
			return $suite;
		}

		return parent::suite( $className );
	}

	function setUp() {
		global $wgHooks;

		parent::setUp();

		// Hook to inject our interwiki prefix
		$this->hooks = $wgHooks;
		$wgHooks['InterwikiLoadPrefix'][] = function ( $prefix, &$data ) {
			if ( $prefix !== 'scribuntotitletest' ) {
				return true;
			}

			$data = array(
				'iw_prefix' => 'scribuntotitletest',
				'iw_url'    => '//test.wikipedia.org/wiki/$1',
				'iw_api'    => 1,
				'iw_wikiid' => 0,
				'iw_local'  => 0,
				'iw_trans'  => 0,
			);
			return false;
		};

		// Page for getContent test
		$page = WikiPage::factory( Title::newFromText( 'ScribuntoTestPage' ) );
		$page->doEditContent(
			new WikitextContent( '{{int:mainpage}}<includeonly>...</includeonly><noinclude>...</noinclude>' ),
			'Summary'
		);

		// Note this depends on every iteration of the data provider running with a clean parser
		$this->getEngine()->getParser()->getOptions()->setExpensiveParserFunctionLimit( 10 );

		// Indicate to the tests that it's safe to create the title objects
		$interpreter = $this->getEngine()->getInterpreter();
		$interpreter->callFunction(
			$interpreter->loadString( 'mw.ok = true', 'fortest' )
		);

		$this->setMwGlobals( array(
			'wgServer' => '//wiki.local',
			'wgCanonicalServer' => 'http://wiki.local',
			'wgUsePathInfo' => true,
			'wgActionPaths' => array(),
			'wgScript' => '/w/index.php',
			'wgScriptPath' => '/w',
			'wgArticlePath' => '/wiki/$1',
		) );
	}

	function tearDown() {
		global $wgHooks;
		$wgHooks = $this->hooks;
		parent::tearDown();
	}

	function getTestModules() {
		return parent::getTestModules() + array(
			'TitleLibraryTests' => __DIR__ . '/TitleLibraryTests.lua',
		);
	}

	function testAddsLinks() {
		$engine = $this->getEngine();
		$interpreter = $engine->getInterpreter();

		$links = $engine->getParser()->getOutput()->getLinks();
		$this->assertFalse( isset( $links[NS_PROJECT]['Referenced_from_Lua'] ) );

		$interpreter->callFunction(
			$interpreter->loadString( 'mw.title.new( "Project:Referenced from Lua" )', 'reference title' )
		);

		$links = $engine->getParser()->getOutput()->getLinks();
		$this->assertArrayHasKey( NS_PROJECT, $links );
		$this->assertArrayHasKey( 'Referenced_from_Lua', $links[NS_PROJECT] );
	}
}
