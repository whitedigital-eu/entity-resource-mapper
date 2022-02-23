<?php

use PHPUnit\Framework\TestCase;
use WhiteDigital\EntityDtoMapper\Filters\DtoTestFilter;

class FilterTest extends TestCase
{
  public function testOne()
  {
      $x = new DtoTestFilter();
      $ret = $x->test();
      self::assertEquals('asdf',$ret);
  }
}
