<?php
namespace Rindow\Module\ZipStream;

class ZipStreamFactory
{
	public function create($filename=null)
	{
		return new ZipStream($filename);
	}
}