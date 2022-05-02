<?php

namespace WhiteDigital\Tests;

use PHPUnit\Framework\TestCase;
use WhiteDigital\EntityResourceMapper\Filters\DtoTestFilter;

class FilterTest extends TestCase
{
  public function testOne()
  {
      $x = new DtoTestFilter();
      $ret = $x->test();
      self::assertEquals('asdf',$ret);
  }
}
