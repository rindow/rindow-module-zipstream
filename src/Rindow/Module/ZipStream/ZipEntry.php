<?php
namespace Rindow\Module\ZipStream;

use Rindow\Module\ZipStream\Exception;

class ZipEntry
{
    const MODE_READ = 1;
    const MODE_WRITE = 2;

    const HDR_SIGNATURE  = 'signature';
    const HDR_VERSION    = 'version';
    const HDR_EXTRACT_VERSION = 'ext-ver';
    const HDR_FLAGS      = 'flags';
    const HDR_COMPRESION = 'compresion';
    const HDR_DOSTIME    = 'dostime';
    const HDR_CRC32      = 'crc32';
    const HDR_COMPRESSED_LENGTH   = 'comp-len';
    const HDR_UNCOMPRESSED_LENGTH = 'uncomp-len';
    const HDR_FILENAME_LENGTH     = 'name-len';
    const HDR_EXTRA_DATA_LENGTH   = 'extra-len';
    const HDR_COMMENT_LENGTH      = 'comment-len';
    const HDR_DISK_NUMBER_START   = 'disk-number-start';
    const HDR_INTERNAL_FILE_ATTRIBUTES = 'internal-file-attributes';
    const HDR_EXTERNAL_FILE_ATTRIBUTES = 'external-file-attributes';
    const HDR_LOCAL_HEADER_OFFSET = 'local-header-offset';
    const HDR_DISK_NUMBER         = 'disk-number';
    const HDR_DISK_START          = 'disk-start';
    const HDR_ENTRIES_THIS_DISK   = 'entries-this-disk';
    const HDR_ENTRIES_TOTAL       = 'entries-total';
    const HDR_DIR_SIZE            = 'dir_size';
    const HDR_DIR_OFFSET          = 'dir_offset';

    const SIG_FILE_HEADER = 0x04034b50;
    const SIG_CENTRAL_DIR = 0x02014b50;
    const SIG_END_ARCHIVE = 0x06054b50;
    const HLEN_FILE_HEADER = 26;
    const HLEN_CENTRAL_DIR = 42;
    const HLEN_END_ARCHIVE = 18;
    
    const CM_STORE   = 0;
    const CM_DEFLATE = 8;

    protected static $fileHeaderFormat = array(
        self::HDR_VERSION           => 'v', // 2 .. 6
        self::HDR_FLAGS             => 'v', // 2 .. 8
        self::HDR_COMPRESION        => 'v', // 2 .. 10
        self::HDR_DOSTIME           => 'V', // 4 .. 14
        self::HDR_CRC32             => 'V', // 4 .. 18
        self::HDR_COMPRESSED_LENGTH => 'V', // 4 .. 22
        self::HDR_UNCOMPRESSED_LENGTH => 'V', // 4 .. 26
        self::HDR_FILENAME_LENGTH   => 'v', // 2 .. 28
        self::HDR_EXTRA_DATA_LENGTH => 'v', // 2 .. 30
    );

    protected static $centralDirFormat = array(
        self::HDR_VERSION           => 'v', // 2 .. 6
        self::HDR_EXTRACT_VERSION   => 'v', // 2 .. 8
        self::HDR_FLAGS             => 'v', // 2 .. 10
        self::HDR_COMPRESION        => 'v', // 2 .. 12
        self::HDR_DOSTIME           => 'V', // 4 .. 16
        self::HDR_CRC32             => 'V', // 4 .. 20
        self::HDR_COMPRESSED_LENGTH => 'V', // 4 .. 24
        self::HDR_UNCOMPRESSED_LENGTH => 'V', // 4 .. 28
        self::HDR_FILENAME_LENGTH   => 'v', // 2 .. 30
        self::HDR_EXTRA_DATA_LENGTH => 'v', // 2 .. 32
        self::HDR_COMMENT_LENGTH    => 'v', // 2 .. 34
        self::HDR_DISK_NUMBER_START => 'v', // 2 .. 36
        self::HDR_INTERNAL_FILE_ATTRIBUTES => 'v', // 2 .. 38
        self::HDR_EXTERNAL_FILE_ATTRIBUTES => 'V', // 4 .. 42
        self::HDR_LOCAL_HEADER_OFFSET => 'V', // 4 .. 46
    );

    protected static $endArchiveFormat = array(
        self::HDR_DISK_NUMBER       => 'v', // 2 .. 6
        self::HDR_DISK_START        => 'v', // 2 .. 8
        self::HDR_ENTRIES_THIS_DISK => 'v', // 2 .. 10
        self::HDR_ENTRIES_TOTAL     => 'v', // 4 .. 12
        self::HDR_DIR_SIZE          => 'V', // 4 .. 16
        self::HDR_DIR_OFFSET        => 'V', // 4 .. 20
        self::HDR_COMMENT_LENGTH    => 'v', // 4 .. 22
    );

    protected $stream;
    protected $signature;
    protected $headers;
    protected $filename;
    protected $extraData;
    protected $comment;
    protected $compressed;
    protected $synchronized = false;
    protected $mode;

    public function __construct($stream)
    {
        $this->stream = $stream;
    }

    protected function assertReadMode()
    {
        if($this->mode==self::MODE_READ)
            return;
        if($this->mode!==null)
            throw new Exception\DomainException('mode is fixed.:'.$this->mode);
        $this->mode = self::MODE_READ;
    }

    protected function assertWriteMode()
    {
        if($this->mode==self::MODE_WRITE)
            return;
        if($this->mode!==null)
            throw new Exception\DomainException('mode is fixed.:'.$this->mode);
        $this->mode = self::MODE_WRITE;
    }

    protected function fread($stream,$length)
    {
        $content = '';
        $total = $length;
        while(true) {
            $buf=fread($stream,$length);
            if(!$buf)
                return $content;
            $content .= $buf;
            $length -= strlen($buf);
            if($length <= 0)
                return $content;
        }
    }

    public function _fetch()
    {
        $this->assertReadMode();
        if($this->synchronized)
            return false;
        if(!$this->readHeaders())
            return false;
        if($this->signature==self::SIG_FILE_HEADER)
            $this->readData();
        $this->synchronized = true;
        return true;
    }

    protected function readHeaders()
    {
        $signature = $this->fread($this->stream, 4);
        if($signature===false || strlen($signature)==0)
            return false;
        $signature = unpack('Vsig',$signature);
        $signature = $signature['sig'];
        switch ($signature) {
            case self::SIG_FILE_HEADER:
                $headerFormat = self::$fileHeaderFormat;
                $headerLength = self::HLEN_FILE_HEADER;
                break;
            case self::SIG_CENTRAL_DIR:
                $headerFormat = self::$centralDirFormat;
                $headerLength = self::HLEN_CENTRAL_DIR;
                break;
            case self::SIG_END_ARCHIVE:
                $headerFormat = self::$endArchiveFormat;
                $headerLength = self::HLEN_END_ARCHIVE;
                break;
            
            default:
                throw new Exception\RuntimeException('invalid header signature:"'.dechex($signature).'"');
        }
        $this->signature = $signature;

        $headerBlock = $this->fread($this->stream, $headerLength);
        if($headerBlock===false)
            throw new Exception\RuntimeException('invalid header block');
        $format = '';
        foreach($headerFormat as $name => $fmt) {
            if($format!='')
                $format .= '/';
            $format .= $fmt.$name;
        }
        $this->headers = unpack($format,$headerBlock);
        if(isset($this->headers[self::HDR_FILENAME_LENGTH]) && $this->headers[self::HDR_FILENAME_LENGTH]) {
            $this->filename = $this->fread($this->stream,$this->headers[self::HDR_FILENAME_LENGTH]);
            if($this->filename===false || strlen($this->filename)!=$this->headers[self::HDR_FILENAME_LENGTH])
                throw new Exception\RuntimeException('invalid header filename');
        }
        if(isset($this->headers[self::HDR_EXTRA_DATA_LENGTH]) && $this->headers[self::HDR_EXTRA_DATA_LENGTH]) {
            $this->extraData = $this->fread($this->stream,$this->headers[self::HDR_EXTRA_DATA_LENGTH]);
            if($this->extraData===false || strlen($this->extraData)!=$this->headers[self::HDR_EXTRA_DATA_LENGTH])
                throw new Exception\RuntimeException('invalid header format');
        }
        if(isset($this->headers[self::HDR_COMMENT_LENGTH]) && $this->headers[self::HDR_COMMENT_LENGTH]) {
            $this->comment = $this->fread($this->stream,$this->headers[self::HDR_COMMENT_LENGTH]);
            if($this->comment===false || strlen($this->comment)!=$this->headers[self::HDR_COMMENT_LENGTH])
                throw new Exception\RuntimeException('invalid header format');
        }
        return true;
    }

    protected function readData()
    {
        if(!is_array($this->headers) || !array_key_exists(self::HDR_COMPRESSED_LENGTH, $this->headers))
            throw new Exception\RuntimeException('not initialized');
        $this->compressed = $this->fread($this->stream, $this->headers[self::HDR_COMPRESSED_LENGTH]);
        if($this->compressed===false || strlen($this->compressed)!=$this->headers[self::HDR_COMPRESSED_LENGTH])
            throw new Exception\RuntimeException('read error');
        return true;
    }

    public function getSignature()
    {
        return $this->signature;
    }

    public function isFile()
    {
        return ($this->signature == self::SIG_FILE_HEADER)? true : false;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function getTimestamp()
    {
        if(!isset($this->headers[self::HDR_DOSTIME]))
            return null;
        $dostime = $this->headers[self::HDR_DOSTIME];
        // 10987654 32109876 54321098 76543210
        // YYYYYYYM MMMDDDDD HHHHHMMM MMMSSSSS
        $year  = (($dostime >> 25) & 0x07f) + 1980;
        $mon   = ($dostime >> 21) & 0x0f;
        $mday  = ($dostime >> 16) & 0x1f;
        $hours = ($dostime >> 11) & 0x1f;
        $minutes = ($dostime >> 5) & 0x3f;
        $seconds = ($dostime << 1) & 0x3f;
        return mktime($hours,$minutes,$seconds,$mon,$mday,$year);
    }

    public function getFilename()
    {
        return $this->filename;
    }

    public function getComment()
    {
        return $this->comment;
    }

    public function getContent()
    {
        if($this->signature!=self::SIG_FILE_HEADER)
            return $this->compressed;
        if(!is_array($this->headers) || !array_key_exists(self::HDR_COMPRESION, $this->headers))
            throw new Exception\RuntimeException('not initialized');
        switch($this->headers[self::HDR_COMPRESION]) {
            case self::CM_STORE:
                if(crc32($this->compressed)!=$this->headers[self::HDR_CRC32])
                    throw new Exception\RuntimeException('crc error');
                return $this->compressed;
            case self::CM_DEFLATE:
                $content = gzinflate($this->compressed);
                if(crc32($content)!=$this->headers[self::HDR_CRC32])
                    throw new Exception\RuntimeException('crc error');
                return $content;
            default:
                throw new Exception\RuntimeException('unsupported compression');
        }
    }

    public function getConentSize()
    {
        if(isset($this->headers[self::HDR_UNCOMPRESSED_LENGTH]))
            return $this->headers[self::HDR_UNCOMPRESSED_LENGTH];
        else
            return null;
    }

    public function setSignature($signature)
    {
        $this->assertWriteMode();
        if($this->signature == $signature)
            return;
        $this->signature = $signature;
        $this->synchronized = false;
        return $this;
    }

    public function setContent($content,$compression=null)
    {
        $this->assertWriteMode();
        if($compression==null)
            $compression = self::CM_DEFLATE;
        switch($compression) {
            case self::CM_STORE:
                $this->compressed = $content;
                break;
            case self::CM_DEFLATE:
                $this->compressed = gzdeflate($content);
                break;
            default:
                throw new Exception\RuntimeException('unsupported compression');
        }
        $this->headers[self::HDR_COMPRESION] = $compression;
        $this->headers[self::HDR_CRC32] = crc32($content);
        $this->headers[self::HDR_COMPRESSED_LENGTH] = strlen($this->compressed);
        $this->headers[self::HDR_UNCOMPRESSED_LENGTH] = strlen($content);
        return $this;
    }

    public function setFilename($filename)
    {
        $this->assertWriteMode();
        $this->filename = $filename;
        $this->headers[self::HDR_FILENAME_LENGTH] = strlen($filename);
        return $this;
    }

    public function setTimestamp($timestamp)
    {
        $this->assertWriteMode();
        $date = getdate();
        extract($date);
        // 10987654 32109876 54321098 76543210
        // YYYYYYYM MMMDDDDD HHHHHMMM MMMSSSSS
        $dostime = ($year-1980)<<25;
        $dostime |= $mon << 21;
        $dostime |= $mday << 16;
        $dostime |= $hours << 11;
        $dostime |= $minutes << 5;
        $dostime |= $seconds >> 1;
        $this->headers[self::HDR_DOSTIME] = $dostime;
        return $this;
    }

    public function setComment($comment)
    {
        $this->assertWriteMode();
        $this->comment = $comment;
        $this->headers[self::HDR_COMMENT_LENGTH] = strlen($comment);
        return $this;
    }

    protected function fwrite($stream,$content)
    {
        $total = 0;
        while(true) {
            $bytes = fwrite($stream, $content);
            if($bytes===false)
                return false;
            $total += $bytes;
            if($bytes>=strlen($content))
                return $total;
            $content = substr($content, $bytes);
            if(strlen($content)<=0)
                return $total;
        }
    }

    public function flush()
    {
        $this->assertWriteMode();
        if($this->synchronized)
            return;
        $this->headers[self::HDR_INTERNAL_FILE_ATTRIBUTES] = 1;
        $this->headers[self::HDR_EXTERNAL_FILE_ATTRIBUTES] = 32;
        $this->writeHeaders();
        if($this->signature==self::SIG_FILE_HEADER)
            $this->writeData();
        $this->compressed = null;
        $this->synchronized = true;
        return $this;
    }

    protected function writeHeaders()
    {
        switch ($this->signature) {
            case self::SIG_FILE_HEADER:
                $headerFormat = self::$fileHeaderFormat;
                $headerLength = self::HLEN_FILE_HEADER;
                break;
            case self::SIG_CENTRAL_DIR:
                $headerFormat = self::$centralDirFormat;
                $headerLength = self::HLEN_CENTRAL_DIR;
                break;
            case self::SIG_END_ARCHIVE:
                $headerFormat = self::$endArchiveFormat;
                $headerLength = self::HLEN_END_ARCHIVE;
                break;
            default:
                throw new Exception\RuntimeException('invalid header signature:"'.dechex($signature).'"');
        }
        $this->headers[self::HDR_VERSION] = 20;
        $this->headers[self::HDR_EXTRACT_VERSION] = 20;
        if(!isset($this->headers[self::HDR_DOSTIME]))
            $this->setTimestamp(time());

        $format = 'V';
        $args = array($this->signature);
        foreach ($headerFormat as $name => $fmt) {
            $format .= $fmt;
            if(isset($this->headers[$name]))
                $args[] = $this->headers[$name];
            else
                $args[] = 0;
        }
        array_unshift($args,$format);
        $header = call_user_func_array('pack',$args);

        if($this->filename) {
            if($this->signature==self::SIG_FILE_HEADER ||
                $this->signature==self::SIG_CENTRAL_DIR) {
                $header .= $this->filename;
            }
        }
        if($this->extraData) {
            if($this->signature==self::SIG_FILE_HEADER ||
                $this->signature==self::SIG_CENTRAL_DIR) {
                $header .= $this->extraData;
            }
        }
        if($this->comment) {
            if($this->signature==self::SIG_CENTRAL_DIR ||
                $this->signature==self::SIG_END_ARCHIVE) {
                $header .= $this->comment;
            }
        }
        if($this->fwrite($this->stream, $header)===false)
            throw new Exception\RuntimeException('write error:'.$this->filename);
    }

    protected function writeData()
    {
        $bytes = $this->fwrite($this->stream, $this->compressed);
    }

    public function getEntrySize()
    {
        switch ($this->signature) {
            case self::SIG_FILE_HEADER:
                return 4 + self::HLEN_FILE_HEADER + 
                    strlen($this->filename) + strlen($this->extraData) + 
                    $this->headers[self::HDR_COMPRESSED_LENGTH];
            case self::SIG_CENTRAL_DIR:
                return 4 + self::HLEN_CENTRAL_DIR + 
                    strlen($this->filename) + strlen($this->extraData) + 
                    strlen($this->comment);
            case self::SIG_END_ARCHIVE:
                return 4 + self::HLEN_END_ARCHIVE + strlen($this->comment);
            default:
                throw new Exception\RuntimeException('invalid header signature:"'.dechex($this->signature).'"');
        }
    }

    public function _setOffset($offset)
    {
        $this->assertWriteMode();
        $this->headers[self::HDR_LOCAL_HEADER_OFFSET] = $offset;
        return $this;
    }

    public function setNumberOfEntries($count)
    {
        $this->assertWriteMode();
        if($this->signature!=self::SIG_END_ARCHIVE)
            throw new Exception\RuntimeException('invalid header signature:"'.dechex($this->signature).'"');
        $this->headers[self::HDR_ENTRIES_THIS_DISK] = $count;
        $this->headers[self::HDR_ENTRIES_TOTAL] = $count;
        return $this;
    }

    public function setCentralDirOffset($offset,$size)
    {
        $this->assertWriteMode();
        if($this->signature!=self::SIG_END_ARCHIVE)
            throw new Exception\RuntimeException('invalid header signature:"'.dechex($this->signature).'"');
        $this->headers[self::HDR_DIR_OFFSET] = $offset;
        $this->headers[self::HDR_DIR_SIZE] = $size;
        return $this;
    }

    public function close()
    {
        $this->stream = null;
        $this->compressed = null;
    }
}