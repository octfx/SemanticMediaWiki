<?php

namespace SMW\Tests\SQLStore;

use Onoi\MessageReporter\MessageReporterFactory;
use SMW\SQLStore\Installer;
use SMW\Tests\TestEnvironment;

/**
 * @covers \SMW\SQLStore\Installer
 * @group semantic-mediawiki
 *
 * @license GNU GPL v2+
 * @since 2.5
 *
 * @author mwjames
 */
class InstallerTest extends \PHPUnit_Framework_TestCase {

	private $spyMessageReporter;
	private $testEnvironment;
	private $tableSchemaManager;
	private $tableBuilder;
	private $tableIntegrityExaminer;
	private $SetupFile;

	protected function setUp() {
		parent::setUp();
		$this->testEnvironment = new TestEnvironment();
		$this->spyMessageReporter = MessageReporterFactory::getInstance()->newSpyMessageReporter();

		$this->tableSchemaManager = $this->getMockBuilder( '\SMW\SQLStore\TableSchemaManager' )
			->disableOriginalConstructor()
			->getMock();

		$this->tableSchemaManager->expects( $this->any() )
			->method( 'getOrphanedTables' )
			->will( $this->returnValue( [] ) );

		$this->tableBuilder = $this->getMockBuilder( '\SMW\SQLStore\TableBuilder' )
			->disableOriginalConstructor()
			->getMock();

		$this->tableIntegrityExaminer = $this->getMockBuilder( '\SMW\SQLStore\TableIntegrityExaminer' )
			->disableOriginalConstructor()
			->getMock();

		$this->jobQueue = $this->getMockBuilder( '\SMW\MediaWiki\JobQueue' )
			->disableOriginalConstructor()
			->getMock();

		$this->setupFile = $this->getMockBuilder( '\SMW\SetupFile' )
			->disableOriginalConstructor()
			->getMock();

		$this->testEnvironment->registerObject( 'JobQueue', $this->jobQueue );
	}

	public function testCanConstruct() {

		$this->assertInstanceOf(
			Installer::class,
			new Installer( $this->tableSchemaManager, $this->tableBuilder, $this->tableIntegrityExaminer )
		);
	}

	public function testInstall() {

		$table = $this->getMockBuilder( '\SMW\SQLStore\TableBuilder\Table' )
			->disableOriginalConstructor()
			->getMock();

		$this->tableSchemaManager->expects( $this->atLeastOnce() )
			->method( 'getTables' )
			->will( $this->returnValue( [ $table ] ) );

		$tableBuilder = $this->getMockBuilder( '\SMW\SQLStore\TableBuilder\TableBuilder' )
			->disableOriginalConstructor()
			->setMethods( [ 'create' ] )
			->getMockForAbstractClass();

		$tableBuilder->expects( $this->once() )
			->method( 'create' );

		$this->tableIntegrityExaminer->expects( $this->once() )
			->method( 'checkOnPostCreation' );

		$instance = new Installer(
			$this->tableSchemaManager,
			$tableBuilder,
			$this->tableIntegrityExaminer
		);

		$instance->setMessageReporter( $this->spyMessageReporter );
		$instance->setSetupFile( $this->setupFile );

		$instance->setOptions(
			[
				Installer::OPT_SCHEMA_UPDATE => false
			]
		);

		$this->assertTrue(
			$instance->install()
		);
	}

	public function testUninstall_OrphanedTables() {

		$tableSchemaManager = $this->getMockBuilder( '\SMW\SQLStore\TableSchemaManager' )
			->disableOriginalConstructor()
			->getMock();

		$table = $this->getMockBuilder( '\SMW\SQLStore\TableBuilder\Table' )
			->disableOriginalConstructor()
			->getMock();

		$tableSchemaManager->expects( $this->atLeastOnce() )
			->method( 'getTables' )
			->will( $this->returnValue( [] ) );

		$tableSchemaManager->expects( $this->atLeastOnce() )
			->method( 'getOrphanedTables' )
			->will( $this->returnValue( [ 'smw_foo' => $table ] ) );

		$tableBuilder = $this->getMockBuilder( '\SMW\SQLStore\TableBuilder\TableBuilder' )
			->disableOriginalConstructor()
			->setMethods( [ 'create', 'drop' ] )
			->getMockForAbstractClass();

		$tableBuilder->expects( $this->once() )
			->method( 'drop' )
			->with($this->equalTo( $table ) );

		$this->tableIntegrityExaminer->expects( $this->once() )
			->method( 'checkOnPostDestruction' );

		$instance = new Installer(
			$tableSchemaManager,
			$tableBuilder,
			$this->tableIntegrityExaminer
		);

		$instance->setMessageReporter( $this->spyMessageReporter );
		$instance->uninstall();
	}

	public function testInstallWithSupplementJobs() {

		$this->jobQueue->expects( $this->exactly( 2 ) )
			->method( 'push' );

		$table = $this->getMockBuilder( '\SMW\SQLStore\TableBuilder\Table' )
			->disableOriginalConstructor()
			->getMock();

		$this->tableSchemaManager->expects( $this->atLeastOnce() )
			->method( 'getTables' )
			->will( $this->returnValue( [ $table ] ) );

		$tableBuilder = $this->getMockBuilder( '\SMW\SQLStore\TableBuilder\TableBuilder' )
			->disableOriginalConstructor()
			->setMethods( [ 'create' ] )
			->getMockForAbstractClass();

		$tableBuilder->expects( $this->once() )
			->method( 'create' );

		$this->tableIntegrityExaminer->expects( $this->once() )
			->method( 'checkOnPostCreation' );

		$instance = new Installer(
			$this->tableSchemaManager,
			$tableBuilder,
			$this->tableIntegrityExaminer
		);

		$instance->setMessageReporter( $this->spyMessageReporter );
		$instance->setSetupFile( $this->setupFile );

		$instance->setOptions(
			[
				Installer::OPT_SUPPLEMENT_JOBS => true
			]
		);

		$instance->install();
	}

	public function testInstallNonVerbose() {

		$table = $this->getMockBuilder( '\SMW\SQLStore\TableBuilder\Table' )
			->disableOriginalConstructor()
			->getMock();

		$this->tableSchemaManager->expects( $this->atLeastOnce() )
			->method( 'getTables' )
			->will( $this->returnValue( [ $table ] ) );

		$tableBuilder = $this->getMockBuilder( '\SMW\SQLStore\TableBuilder\TableBuilder' )
			->disableOriginalConstructor()
			->setMethods( [ 'create' ] )
			->getMockForAbstractClass();

		$instance = new Installer(
			$this->tableSchemaManager,
			$tableBuilder,
			$this->tableIntegrityExaminer
		);

		$instance->setMessageReporter( $this->spyMessageReporter );
		$instance->setSetupFile( $this->setupFile );

		$this->assertTrue(
			$instance->install( false )
		);
	}

	public function testUninstall() {

		$table = $this->getMockBuilder( '\SMW\SQLStore\TableBuilder\Table' )
			->disableOriginalConstructor()
			->getMock();

		$this->tableSchemaManager->expects( $this->once() )
			->method( 'getTables' )
			->will( $this->returnValue( [ $table ] ) );

		$tableBuilder = $this->getMockBuilder( '\SMW\SQLStore\TableBuilder\TableBuilder' )
			->disableOriginalConstructor()
			->setMethods( [ 'drop' ] )
			->getMockForAbstractClass();

		$tableBuilder->expects( $this->once() )
			->method( 'drop' );

		$instance = new Installer(
			$this->tableSchemaManager,
			$tableBuilder,
			$this->tableIntegrityExaminer
		);

		$instance->setMessageReporter( $this->spyMessageReporter );

		$this->assertTrue(
			$instance->uninstall()
		);
	}

	public function testReportMessage() {

		$instance = new Installer(
			$this->tableSchemaManager,
			$this->tableBuilder,
			$this->tableIntegrityExaminer
		);

		$callback = function() use( $instance ) {
			$instance->reportMessage( 'Foo' );
		};

		$this->assertEquals(
			'Foo',
			$this->testEnvironment->outputFromCallbackExec( $callback )
		);
	}

}
