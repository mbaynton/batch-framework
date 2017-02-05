<?php


namespace mbaynton\BatchFramework\Tests\Unit\Controller;


use mbaynton\BatchFramework\Controller\HttpRunnerControllerTrait;
use mbaynton\BatchFramework\Internal\FunctionWrappers;

class HttpRunnerControllerTraitTest extends \PHPUnit_Framework_TestCase {

  protected function getFnWrapperMock() {
    $fn_wrapper = $this->getMockBuilder('\mbaynton\BatchFramework\Internal\FunctionWrappers')
      ->setProxyTarget(FunctionWrappers::singleton())
      ->enableProxyingToOriginalMethods()
      ->getMock();
    return $fn_wrapper;
  }

  protected function sutFactory($abort_behavior, $fn_wrapper = NULL) {
    if ($fn_wrapper === NULL) {
      $fn_wrapper = $this->getFnWrapperMock();
    }

    $runner = $this->getMock('\mbaynton\BatchFramework\RunnerInterface',
      ['getIncarnationTargetRuntime', 'getRunnerId', 'run']
    );
    $runner->method('getIncarnationTargetRuntime')->willReturn(30);
    $runner->method('getRunnerId')->willReturn(1);
    return new HttpRunnerController($abort_behavior, $fn_wrapper, $runner);
  }

  public function testPhpExecutionTimeIs2xTarget() {
    $wrapper = $this->getFnWrapperMock();
    $wrapper->expects($this->once())
      ->method('set_time_limit')
      ->with(60);
    $this->sutFactory(NULL, $wrapper);
  }

  public function testHalt() {
    $wrapper = $this->getFnWrapperMock();
    $wrapper->expects($this->never())->method('ignore_user_abort');
    $sut = $this->sutFactory(HttpRunnerController::$ONABORTED_HALT, $wrapper);
    $this->assertTrue($sut->shouldContinueRunning());
  }

  public function testCleanstop() {
    $wrapper = $this->getFnWrapperMock();
    $wrapper->expects($this->once())
      ->method('ignore_user_abort')
      ->with(TRUE);
    $this->sutFactory(HttpRunnerControllerTrait::$ONABORTED_CLEANSTOP, $wrapper);
  }

  public function testAbortIgnore_Ignore() {
    $wrapper = $this->getFnWrapperMock();
    $wrapper->expects($this->once())
      ->method('ignore_user_abort')
      ->with(TRUE);
    $sut = $this->sutFactory(HttpRunnerControllerTrait::$ONABORTED_IGNORE, $wrapper);
    $this->assertTrue($sut->shouldContinueRunning());
  }

  public function testCleanStop_StopsOnAbort() {
    $wrapper = $this->getMock('\mbaynton\BatchFramework\Internal\FunctionWrappers', ['connection_aborted']);
    $wrapper->method('connection_aborted')->will($this->onConsecutiveCalls(FALSE, TRUE));
    $wrapper->expects($this->exactly(2))->method('connection_aborted');

    $sut = $this->sutFactory(HttpRunnerControllerTrait::$ONABORTED_CLEANSTOP, $wrapper);
    $this->assertTrue($sut->shouldContinueRunning());
    $this->assertFalse($sut->shouldContinueRunning());
  }
}
