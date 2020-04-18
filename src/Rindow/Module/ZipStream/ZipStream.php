<?php
namespace Rindow\Module\ZipStream;

class ZipStream
{
    protected $stream;
    protected $entries = array();

    public function __construct($filename=null)
    {
        if(is_resource($filename))
            $this->setStream($filename);
        elseif($filename)
            $this->open($filename,'rb');
    }

    public function open($filename, $mode, $useIncludePath=null, $context=null)
    {
        if($useIncludePath==null)
            $useIncludePath = false;
        if($context==null)
            $this->stream = fopen($filename, $mode, $useIncludePath);
        else
            $this->stream = fopen($filename, $mode, $useIncludePath, $context);
        return $this->stream;
    }

    public function setStream($stream)
    {
        $this->stream = $stream;
    }

    public function getStream()
    {
        return $this->stream;
    }

    public function getEntry()
    {
        $entry = new ZipEntry($this->stream);
        if(!$entry->_fetch()) {
            $entry->close();
            return false;
        }
        if($entry->getSignature()==ZipEntry::SIG_END_ARCHIVE) {
            //var_dump($entry->getHeaders());
            $entry->close();
            return false;
        }
        return $entry;
    }

    public function addFile($filename, $localname=null, $start=null, $length=null)
    {
        if($localname==null)
            $localname = $filename;
        $stream = fopen($filename,'rb');
        if($stream===false)
            throw new Exception\DomainException('open error:'.$filename);
        if($start!==null) {
            if(fseek($stream, $start))
                throw new Exception\DomainException('seek error. offset='.$start.':'.$filename);
        }
        $entry = $this->addFromStream($localname,$stream,$length);
        fclose($stream);
        return $entry;
    }

    public function addFromStream($localname, $stream, $length=null)
    {
        $contents = '';
        if($length===null)
            $blocksize = 8192;
        else
            $blocksize = $length;
        while(true) {
            $buffer = fread($stream,$blocksize);
            if($buffer===false)
                throw new Exception\RuntimeException('read error:'.$localname);
            if(strlen($buffer)==0)
                break;
            $contents .= $buffer;
            if($length!==null) {
                $leftsize = $length - strlen($contents);
                if($leftsize <= 0)
                    break;
                if($leftsize<$blocksize)
                    $blocksize = $leftsize;
            }
        }
        return $this->addFromString($localname,$contents);
    }

    public function addFromString($localname, $contents)
    {
        $entry = new ZipEntry($this->stream);
        $entry->setSignature(ZipEntry::SIG_FILE_HEADER);
        $entry->setFilename($localname);
        $entry->setContent($contents,ZipEntry::CM_DEFLATE);
        $this->entries[] = $entry;
        return $entry;
    }

    public function flush()
    {
        $offset = 0;
        foreach($this->entries as $entry) {
            if($entry->getSignature()!=ZipEntry::SIG_FILE_HEADER)
                throw new Exception\DomainException('must be file entry');
            $entry->_setOffset($offset);
            $entry->flush();
            $offset += $entry->getEntrySize();
        }
        $dirOffset = $offset;
        foreach($this->entries as $entry) {
            $entry->setSignature(ZipEntry::SIG_CENTRAL_DIR);
            $entry->flush();
            $offset += $entry->getEntrySize();
        }
        $entry = new ZipEntry($this->stream);
        $entry->setSignature(ZipEntry::SIG_END_ARCHIVE);
        $entry->setNumberOfEntries(count($this->entries));
        $entry->setCentralDirOffset($dirOffset,$offset-$dirOffset);
        $entry->flush();
    }


    public function close()
    {
        if(!$this->stream)
            return;
        fclose($this->stream);
        $this->stream = null;
    }
}