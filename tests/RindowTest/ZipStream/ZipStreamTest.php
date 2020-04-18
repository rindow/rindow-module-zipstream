<?php
namespace RindowTest\ZipStream\ZipStreamTest;

use PHPUnit\Framework\TestCase;
use ZipArchive;
use DateTime;
use Rindow\Module\ZipStream\ZipStream;
use Rindow\Module\ZipStream\ZipEntry;

class Test extends TestCase
{
    public function getStream($filename)
    {
        $stream = fopen('php://memory','w+');
        $data = file_get_contents($filename);
        fwrite($stream, $data);
        fseek($stream, 0);
        return $stream;
    }

    public function testReadStream()
    {

        $stream = $this->getStream(__DIR__.'/resources/test.zip');
        $zip = new ZipStream($stream);
        $file1 = $file2 = $file3 = 0;
        while($entry = $zip->getEntry()) {
            if($entry->isFile()) {
                //echo "--------\n";
                //var_dump($entry->getFilename());
                //var_dump($entry->getHeaders());
                //var_dump($timestamp->format(DateTime::W3C));
                if($entry->getFilename()=='testfile1.txt') {
                    $file1++;
                    $timestamp = new DateTime();
                    $timestamp->setTimestamp($entry->getTimestamp());
                    $this->assertEquals('2015-08-06 11:56:26',$timestamp->format('Y-m-d h:i:s'));
                    $this->assertEquals(22,$entry->getConentSize());
                    $this->assertEquals("12345678901234567890\r\n",$entry->getContent());
                } elseif($entry->getFilename()=='testfile2.txt') {
                    $file2++;
                    $timestamp = new DateTime();
                    $timestamp->setTimestamp($entry->getTimestamp());
                    $this->assertEquals('2015-08-06 11:57:34',$timestamp->format('Y-m-d h:i:s'));
                    $this->assertEquals(26,$entry->getConentSize());
                    $this->assertEquals("abcdefghijklmnopqrstuvwxyz",$entry->getContent());
                } elseif($entry->getFilename()=='subdir/testfile3.txt') {
                    $file3++;
                    $timestamp = new DateTime();
                    $timestamp->setTimestamp($entry->getTimestamp());
                    $this->assertEquals('2015-08-06 11:55:00',$timestamp->format('Y-m-d h:i:s'));
                    $this->assertEquals(10,$entry->getConentSize());
                    $this->assertEquals("10byte\r\n\r\n",$entry->getContent());
                } else {
                    $this->assertTrue(false);
                }
            } else {
                $this->assertEquals(ZipEntry::SIG_CENTRAL_DIR,$entry->getSignature());
                //var_dump($entry->getFilename());
                //var_dump($entry->getHeaders());
            }
            $entry->close();
        }
        $zip->close();
        $this->assertEquals(1,$file1);
        $this->assertEquals(1,$file2);
        $this->assertEquals(1,$file3);
    }

    public function testLargefiles()
    {
        $zip = new ZipStream(__DIR__.'/resources/largefiles.zip');
        $file1 = $file2 = $file3 = 0;
        while($entry = $zip->getEntry()) {
            if($entry->isFile()) {
                //echo "--------\n";
                //var_dump($entry->getHeaders());
                //var_dump($entry->getFilename());
                if($entry->getFilename()=='largefile1.txt') {
                    $file1++;
                    $content = $entry->getContent();
                    $this->assertEquals(2883584,$entry->getConentSize());
                    $this->assertEquals(2883584,strlen($content));
                } elseif($entry->getFilename()=='largefile2.txt') {
                    $file2++;
                    $content = $entry->getContent();
                    $this->assertEquals(1441792,$entry->getConentSize());
                    $this->assertEquals(1441792,strlen($content));
                } else {
                    $this->assertTrue(false);
                }
            } else {
                $this->assertEquals(ZipEntry::SIG_CENTRAL_DIR,$entry->getSignature());
                //var_dump($entry->getFilename());
                //var_dump($entry->getHeaders());
            }
            $entry->close();
        }
        $zip->close();
        $this->assertEquals(1,$file1);
        $this->assertEquals(1,$file2);
    }

    public function testWriteStreamFromString()
    {
        $stream = fopen('php://memory','w+');
        $zip = new ZipStream($stream);
        $zip->addFromString('testfile1.txt',"12345678901234567890\r\n");
        $zip->addFromString('testfile2.txt',"abcdefghijklmnopqrstuvwxyz");
        $zip->addFromString('subdir/testfile3.txt',"10byte\r\n\r\n");
        $zip->flush();
        fseek($stream, 0);
        $zipdata = '';
        while($buf=fread($stream,8192)) {
            $zipdata .= $buf;
        }
        fclose($stream);
        file_put_contents(__DIR__.'/../../tmp/tmpfile1.zip', $zipdata);

        //$zip = new ZipStream();
        //$zip->open(__DIR__.'/../../tmp/tmpfile.zip','rb');
        //while($entry=$zip->getEntry()) {
        //    var_dump($entry->getFilename());
        //    var_dump($entry->getHeaders());
        //}
        //$zip->close();

        $file1 = $file2 = $file3 = 0;
        $zip = new ZipArchive();
        $zip->open(__DIR__.'/../../tmp/tmpfile1.zip');
        for($idx=0;$idx<$zip->numFiles;$idx++) {
            $stat = $zip->statIndex( $idx );
            //var_dump($stat);
            if($stat['name']=='testfile1.txt') {
                $file1++;
                $this->assertEquals(22,$stat['size']);
                $this->assertEquals("12345678901234567890\r\n",$zip->getFromIndex($idx));
                $this->assertEquals(ZipArchive::CM_DEFLATE,$stat['comp_method']);
            } elseif($stat['name']=='testfile2.txt') {
                $file2++;
                $this->assertEquals(26,$stat['size']);
                $this->assertEquals("abcdefghijklmnopqrstuvwxyz",$zip->getFromIndex($idx));
                $this->assertEquals(ZipArchive::CM_DEFLATE,$stat['comp_method']);
            } elseif($stat['name']=='subdir/testfile3.txt') {
                $file3++;
                $this->assertEquals(10,$stat['size']);
                $this->assertEquals("10byte\r\n\r\n",$zip->getFromIndex($idx));
                $this->assertEquals(ZipArchive::CM_DEFLATE,$stat['comp_method']);
            } else {
                $this->assertTrue(false);
            }
        }
        $zip->close();
        $this->assertEquals(1,$file1);
        $this->assertEquals(1,$file2);
        $this->assertEquals(1,$file3);
    }

    public function testWriteStreamFromStream()
    {
        $stream = fopen('php://memory','w+');
        $zip = new ZipStream($stream);
        $fp = $this->getStream(__DIR__.'/resources/mergedfile.txt');
        $zip->addFromStream('testfile1.txt',$fp,22);
        $zip->addFromStream('testfile2.txt',$fp,26);
        fclose($fp);
        $fp = $this->getStream(__DIR__.'/resources/subdir/testfile3.txt');
        $zip->addFromStream('subdir/testfile3.txt',$fp);
        fclose($fp);
        $zip->flush();
        fseek($stream, 0);
        $zipdata = '';
        while($buf=fread($stream,8192)) {
            $zipdata .= $buf;
        }
        fclose($stream);
        file_put_contents(__DIR__.'/../../tmp/tmpfile2.zip', $zipdata);

        //$zip = new ZipStream();
        //$zip->open(__DIR__.'/../../tmp/tmpfile.zip','rb');
        //while($entry=$zip->getEntry()) {
        //    var_dump($entry->getFilename());
        //    var_dump($entry->getHeaders());
        //}
        //$zip->close();

        $file1 = $file2 = $file3 = 0;
        $zip = new ZipArchive();
        $zip->open(__DIR__.'/../../tmp/tmpfile2.zip');
        for($idx=0;$idx<$zip->numFiles;$idx++) {
            $stat = $zip->statIndex( $idx );
            //var_dump($stat);
            if($stat['name']=='testfile1.txt') {
                $file1++;
                $this->assertEquals(22,$stat['size']);
                $this->assertEquals("12345678901234567890\r\n",$zip->getFromIndex($idx));
                $this->assertEquals(ZipArchive::CM_DEFLATE,$stat['comp_method']);
            } elseif($stat['name']=='testfile2.txt') {
                $file2++;
                $this->assertEquals(26,$stat['size']);
                $this->assertEquals("abcdefghijklmnopqrstuvwxyz",$zip->getFromIndex($idx));
                $this->assertEquals(ZipArchive::CM_DEFLATE,$stat['comp_method']);
            } elseif($stat['name']=='subdir/testfile3.txt') {
                $file3++;
                $this->assertEquals(10,$stat['size']);
                $this->assertEquals("10byte\r\n\r\n",$zip->getFromIndex($idx));
                $this->assertEquals(ZipArchive::CM_DEFLATE,$stat['comp_method']);
            } else {
                $this->assertTrue(false);
            }
        }
        $zip->close();
        $this->assertEquals(1,$file1);
        $this->assertEquals(1,$file2);
        $this->assertEquals(1,$file3);
    }

    public function testWriteStreamFromFile()
    {
        $stream = fopen('php://memory','w+');
        $zip = new ZipStream($stream);
        $zip->addFile(__DIR__.'/resources/mergedfile.txt','testfile1.txt',$start=0,$length=22);
        $zip->addFile(__DIR__.'/resources/mergedfile.txt','testfile2.txt',$start=22,$length=26);
        $zip->addFile(__DIR__.'/resources/subdir/testfile3.txt','subdir/testfile3.txt');
        $zip->flush();
        fseek($stream, 0);
        $zipdata = '';
        while($buf=fread($stream,8192)) {
            $zipdata .= $buf;
        }
        fclose($stream);
        file_put_contents(__DIR__.'/../../tmp/tmpfile3.zip', $zipdata);

        //$zip = new ZipStream();
        //$zip->open(__DIR__.'/../../tmp/tmpfile.zip','rb');
        //while($entry=$zip->getEntry()) {
        //    var_dump($entry->getFilename());
        //    var_dump($entry->getHeaders());
        //}
        //$zip->close();

        $file1 = $file2 = $file3 = 0;
        $zip = new ZipArchive();
        $zip->open(__DIR__.'/../../tmp/tmpfile3.zip');
        for($idx=0;$idx<$zip->numFiles;$idx++) {
            $stat = $zip->statIndex( $idx );
            //var_dump($stat);
            if($stat['name']=='testfile1.txt') {
                $file1++;
                $this->assertEquals(22,$stat['size']);
                $this->assertEquals("12345678901234567890\r\n",$zip->getFromIndex($idx));
                $this->assertEquals(ZipArchive::CM_DEFLATE,$stat['comp_method']);
            } elseif($stat['name']=='testfile2.txt') {
                $file2++;
                $this->assertEquals(26,$stat['size']);
                $this->assertEquals("abcdefghijklmnopqrstuvwxyz",$zip->getFromIndex($idx));
                $this->assertEquals(ZipArchive::CM_DEFLATE,$stat['comp_method']);
            } elseif($stat['name']=='subdir/testfile3.txt') {
                $file3++;
                $this->assertEquals(10,$stat['size']);
                $this->assertEquals("10byte\r\n\r\n",$zip->getFromIndex($idx));
                $this->assertEquals(ZipArchive::CM_DEFLATE,$stat['comp_method']);
            } else {
                $this->assertTrue(false);
            }
        }
        $zip->close();
        $this->assertEquals(1,$file1);
        $this->assertEquals(1,$file2);
        $this->assertEquals(1,$file3);
    }

    public function testWriteStreamLargeFileWithFlush()
    {
        $stream = fopen('php://memory','w+');
        $zip = new ZipStream($stream);
        $zip->addFile(__DIR__.'/resources/largefile1.txt','largefile1.txt')->flush();
        $zip->addFile(__DIR__.'/resources/largefile2.txt','largefile2.txt')->flush();
        $zip->flush();
        fseek($stream, 0);
        $zipdata = '';
        while($buf=fread($stream,8192)) {
            $zipdata .= $buf;
        }
        fclose($stream);
        file_put_contents(__DIR__.'/../../tmp/tmpfile4.zip', $zipdata);

        //$zip = new ZipStream();
        //$zip->open(__DIR__.'/../../tmp/tmpfile.zip','rb');
        //while($entry=$zip->getEntry()) {
        //    var_dump($entry->getFilename());
        //    var_dump($entry->getHeaders());
        //}
        //$zip->close();

        $file1 = $file2 = $file3 = 0;
        $zip = new ZipArchive();
        $zip->open(__DIR__.'/../../tmp/tmpfile4.zip');
        for($idx=0;$idx<$zip->numFiles;$idx++) {
            $stat = $zip->statIndex( $idx );
            //var_dump($stat);
            if($stat['name']=='largefile1.txt') {
                $file1++;
                $this->assertEquals(2883584,$stat['size']);
                $this->assertEquals(2883584,strlen($zip->getFromIndex($idx)));
                $this->assertEquals(ZipArchive::CM_DEFLATE,$stat['comp_method']);
            } elseif($stat['name']=='largefile2.txt') {
                $file2++;
                $this->assertEquals(1441792,$stat['size']);
                $this->assertEquals(1441792,strlen($zip->getFromIndex($idx)));
                $this->assertEquals(ZipArchive::CM_DEFLATE,$stat['comp_method']);
            } else {
                $this->assertTrue(false);
            }
        }
        $zip->close();
        $this->assertEquals(1,$file1);
        $this->assertEquals(1,$file2);
    }
}
