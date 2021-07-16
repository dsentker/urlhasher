<?php

namespace UrlFingerprintTest;

use PHPUnit\Framework\TestCase;
use UrlFingerprint\Exception\InvalidUrl;
use UrlFingerprint\FingerprintReader;

class FingerprintReaderTest extends TestCase
{
    public function testHashAlgoIsRecognized()
    {
        $reader = new FingerprintReader([
            'secret'    => '42',
            'hash_algo' => 'md5',
        ]);
        $urlHash = $reader->capture('https://www.example.com');

        $this->assertEquals('md5', $urlHash->getHashAlgo());
    }

    /**
     * @dataProvider getUrlsWithQueryToSort
     */
    public function testQueryStringIsSorted(string $expected, string $url, string $message = null)
    {
        $reader = new FingerprintReader([
            'secret'    => '42',
            'hash_algo' => 'md5',
        ]);
        $urlHash = $reader->capture($url);

        $this->assertEquals(
            $expected,
            $urlHash->getGist(),
            $message
        );
    }

    /**
     * @dataProvider getHashOptionsAndExpectedUrls
     */
    public function testHashOptionsAreHonored(string $urlToTest, array $hashOptions, string $expectedSkeleton)
    {
        $reader = new FingerprintReader(array_merge([
            'secret'    => '42',
            'hash_algo' => 'md5',
        ], $hashOptions));
        $urlHash = $reader->capture($urlToTest);

        $this->assertEquals(
            $expectedSkeleton,
            $urlHash->getGist(),
        );
    }

    public function testHashIsValid()
    {
        $reader = new FingerprintReader([
            'secret'    => '42',
            'hash_algo' => 'md5',
        ]);
        $fingerprint = $reader->capture('https://www.example.com');

        $this->assertIsString($fingerprint->getHash());
        $this->assertEquals(32, strlen($fingerprint->getHash()));
    }

    public function testMissingRequiredScheme()
    {
        $this->expectException(InvalidUrl::class);
        $this->expectExceptionMessage('The scheme for url (//www.example.com) is missing!');
        $readerEnforcingScheme = new FingerprintReader([
            'secret'      => '42',
            'hash_algo'   => 'md5',
            'hash_scheme' => true,
        ]);
        $readerEnforcingScheme->capture('//www.example.com');
    }

    public function testMissingScheme()
    {
        $reader = new FingerprintReader([
            'secret'      => '42',
            'hash_algo'   => 'md5',
            'hash_scheme' => false,
        ]);
        $fingerprint = $reader->capture('//www.example.com');

        $this->assertEquals(
            '{"hash_scheme":null,"hash_userinfo":null,"hash_host":"www.example.com","hash_port":null,"hash_path":"","hash_query":null,"hash_fragment":null}',
            $fingerprint->getGist()
        );
    }

    public function testEmptyUri()
    {
        $this->expectException(InvalidUrl::class);
        $this->expectExceptionMessage('The URL string is empty!');
        $fingerprintReader = new FingerprintReader([
            'secret'      => '42',
            'hash_algo'   => 'md5',
            'hash_scheme' => true,
        ]);
        $fingerprintReader->capture('');
    }

    public function testEmptyCharactersSurroundingUrlWillNotAffectTheResult()
    {
        $reader = new FingerprintReader([
            'secret'        => '42',
            'hash_algo'     => 'md5',
            'hash_scheme'   => true,
            'hash_fragment' => true,
        ]);
        $urlHash = $reader->capture(' https://www.example.com/#anchor ');

        $this->assertEquals(
            '{"hash_scheme":"https","hash_userinfo":null,"hash_host":"www.example.com","hash_port":null,"hash_path":"/","hash_query":null,"hash_fragment":"anchor"}',
            $urlHash->getGist()
        );
    }

    public function testUrlWithSyntaxError()
    {
        $this->expectException(InvalidUrl::class);
        $this->expectExceptionMessage('The uri `https://` is invalid for the `https` scheme.');
        $reader = new FingerprintReader([
            'secret'      => '42',
            'hash_algo'   => 'md5',
            'hash_scheme' => true,
        ]);
        $reader->capture('https://');
    }

    public function testUrlPathWithWhitespace()
    {
        $reader = new FingerprintReader([
            'secret'      => '42',
            'hash_algo'   => 'md5',
            'hash_scheme' => false,
        ]);
        $urlHash = $reader->capture('//example.com/foo bar/baz');
        $this->assertEquals(
            '{"hash_scheme":null,"hash_userinfo":null,"hash_host":"example.com","hash_port":null,"hash_path":"/foo%20bar/baz","hash_query":null,"hash_fragment":null}',
            $urlHash->getGist()
        );
    }

    public function testCompareFingerprints()
    {
        $reader = new FingerprintReader([
            'secret'        => '42',
            'hash_algo'     => 'md5',
            'hash_scheme'   => false,
            'hash_query'    => false,
            'hash_fragment' => false,
        ]);
        $urlHash1 = $reader->capture('http://www.example.com/foo/bar/?qux=baz');
        $urlHash2 = $reader->capture('https://www.example.com/foo/bar/#anchor');

        $this->assertTrue(
            $reader->compare($urlHash1, $urlHash2),
            sprintf('Assert that gist %s%s equals gist %s%s.', PHP_EOL, $urlHash1->getGist(), PHP_EOL,
                $urlHash2->getGist())
        );
    }

    public function testCompareFingerprintsShouldPassWithDifferentQueryParameterOrder()
    {
        $reader = new FingerprintReader([
            'secret'        => '42',
            'hash_algo'     => 'md5',
            'hash_query'    => true,
        ]);
        $urlHash1 = $reader->capture('https://www.example.com/foo/?ananas=baz&banana=qux&citrus');
        $urlHash2 = $reader->capture('https://www.example.com/foo/?citrus&banana=qux&ananas=baz');

        $this->assertTrue(
            $reader->compare($urlHash1, $urlHash2),
            sprintf('Assert that gist %s%s equals gist %s%s.', PHP_EOL, $urlHash1->getGist(), PHP_EOL,
                $urlHash2->getGist())
        );
    }

    public function testCompareFingerprintsShouldFailWithDifferentQueryValues()
    {
        $reader = new FingerprintReader([
            'secret'        => '42',
            'hash_algo'     => 'md5',
            'hash_query'    => true,
        ]);
        $urlHash1 = $reader->capture('https://www.example.com/foo/?ananas=&banana=qux&citrus');
        $urlHash2 = $reader->capture('https://www.example.com/foo/?citrus&banana=qux&ananas=baz');

        $this->assertFalse(
            $reader->compare($urlHash1, $urlHash2),
            sprintf('Assert that gist %s%s is not equal to gist %s%s.', PHP_EOL, $urlHash1->getGist(), PHP_EOL,
                $urlHash2->getGist())
        );
    }

    public function getUrlsWithQueryToSort()
    {
        yield [
            '{"hash_scheme":"http","hash_userinfo":null,"hash_host":"example.com","hash_port":null,"hash_path":"","hash_query":null,"hash_fragment":null}',
            'http://example.com',
            'A URL without query string must not be sorted',
        ];
        yield [
            '{"hash_scheme":"http","hash_userinfo":null,"hash_host":"example.com","hash_port":null,"hash_path":"","hash_query":null,"hash_fragment":null}',
            'http://example.com?',
            'A question mark in URL (no slash) without query string should have no effect',
        ];
        yield [
            '{"hash_scheme":"http","hash_userinfo":null,"hash_host":"example.com","hash_port":null,"hash_path":"/","hash_query":null,"hash_fragment":null}',
            'http://example.com/?',
            'A question mark in URL without query string should have no effect',
        ];
        yield [
            '{"hash_scheme":"https","hash_userinfo":null,"hash_host":"example.com","hash_port":null,"hash_path":"/","hash_query":"foo=bar","hash_fragment":null}',
            'https://example.com/?foo=bar',
            'A URL with a single query key-value pair should not be sorted',
        ];
        // Sort is done properly / b=x&a=x will transform to a=x&b=x
        yield [
            '{"hash_scheme":"https","hash_userinfo":null,"hash_host":"example.com","hash_port":null,"hash_path":"/","hash_query":"a=1337&b=42","hash_fragment":null}',
            'https://example.com/?b=42&a=1337',
            'The query string keys should be sorted ASC.',
        ];
        yield [
            '{"hash_scheme":"https","hash_userinfo":null,"hash_host":"example.com","hash_port":null,"hash_path":"/","hash_query":"a=1337&b=42","hash_fragment":null}',
            'https://example.com/?a=1337&b=42',
            'Query string keys already sorted should not be sorted.',
        ];
        // a=x&b= will transform to a=x&b= (always keep equal sign)
        yield [
            '{"hash_scheme":"https","hash_userinfo":null,"hash_host":"example.com","hash_port":null,"hash_path":"/","hash_query":"a=1337&b=","hash_fragment":null}',
            'https://example.com/?a=1337&b=',
            'Query parameters without value should be sorted normally.',
        ];
        // a=x&b will transform to a=x&b=
        yield [
            '{"hash_scheme":"https","hash_userinfo":null,"hash_host":"example.com","hash_port":null,"hash_path":"/","hash_query":"a=1337&b=","hash_fragment":null}',
            'https://example.com/?a=1337&b',
            'Boolean Query parameters (?b instead of ?b=) should be handled properly. ',
        ];
        // a=1&b&a=2 will transform to a=1&a=2&b
        yield [
            '{"hash_scheme":"https","hash_userinfo":null,"hash_host":"example.com","hash_port":null,"hash_path":"/","hash_query":"a=1337&a=42&b=x","hash_fragment":null}',
            'https://example.com/?a=1337&b=x&a=42',
            'Expecting that no',
        ];
        yield [
            '{"hash_scheme":"https","hash_userinfo":null,"hash_host":"example.com","hash_port":null,"hash_path":"/","hash_query":"a=1337&a=42&b=x","hash_fragment":null}',
            'https://example.com/?a=42&b=x&a=1337',
            'Multiple occurences of query keys should be sorted properly.',
        ];
        // sorting is guaranteed with array keys
        yield [
            '{"hash_scheme":"https","hash_userinfo":null,"hash_host":"example.com","hash_port":null,"hash_path":"/","hash_query":"a[]=b&a[]=x","hash_fragment":null}',
            'https://example.com/?a[]=x&a[]=b',
            'Array keys in query string should be sorted properly.',
        ];
        // Keep order with empty values
        yield [
            '{"hash_scheme":"https","hash_userinfo":null,"hash_host":"example.com","hash_port":null,"hash_path":"/","hash_query":"a=&z=","hash_fragment":null}',
            'https://example.com/?z&a',
            'Array keys wihout values should be sorted properly.',
        ];
        yield [
            '{"hash_scheme":"https","hash_userinfo":null,"hash_host":"example.com","hash_port":null,"hash_path":"/","hash_query":"a=-1&a=1","hash_fragment":null}',
            'https://example.com/?a=1&a=-1',
            'Integer values should be handled properly',
        ];
        yield [
            '{"hash_scheme":"https","hash_userinfo":null,"hash_host":"example.com","hash_port":null,"hash_path":"/","hash_query":null,"hash_fragment":null}',
            'https://example.com/#?foo=bar',
            'Irreglar query strings after hash sign must be ignored',
        ];
        yield [
            '{"hash_scheme":"https","hash_userinfo":null,"hash_host":"example.com","hash_port":null,"hash_path":"/","hash_query":null,"hash_fragment":null}',
            'https://example.com/?#?foo=bar',
            'Irreglar query strings after hash sign must be ignored',
        ];
    }


    public function getHashOptionsAndExpectedUrls()
    {
        $url = 'https://user:hunter2@subdomain.example.com:42/path/to?zfoo=bar&qux=baz#anchor';

        $urls = [
            [
                'opts'     => [
                    'hash_scheme'   => true,
                    'hash_userinfo' => false,
                    'hash_host'     => false,
                    'hash_port'     => false,
                    'hash_path'     => false,
                    'hash_query'    => false,
                    'hash_fragment' => false,
                ],
                'expected' => '{"hash_scheme":"https","hash_userinfo":null,"hash_host":null,"hash_port":null,"hash_path":"","hash_query":null,"hash_fragment":null}',
            ],
            [
                'opts'     => [
                    'hash_scheme'   => true,
                    'hash_userinfo' => true,
                    'hash_host'     => false,
                    'hash_port'     => false,
                    'hash_path'     => false,
                    'hash_query'    => false,
                    'hash_fragment' => false,
                ],
                'expected' => '{"hash_scheme":"https","hash_userinfo":"user:hunter2","hash_host":null,"hash_port":null,"hash_path":"","hash_query":null,"hash_fragment":null}',
            ],
            [
                'opts'     => [
                    'hash_scheme'   => true,
                    'hash_userinfo' => true,
                    'hash_host'     => true,
                    'hash_port'     => false,
                    'hash_path'     => false,
                    'hash_query'    => false,
                    'hash_fragment' => false,
                ],
                'expected' => '{"hash_scheme":"https","hash_userinfo":"user:hunter2","hash_host":"subdomain.example.com","hash_port":null,"hash_path":"","hash_query":null,"hash_fragment":null}',
            ],
            [
                'opts'     => [
                    'hash_scheme'   => true,
                    'hash_userinfo' => true,
                    'hash_host'     => true,
                    'hash_port'     => true,
                    'hash_path'     => false,
                    'hash_query'    => false,
                    'hash_fragment' => false,
                ],
                'expected' => '{"hash_scheme":"https","hash_userinfo":"user:hunter2","hash_host":"subdomain.example.com","hash_port":42,"hash_path":"","hash_query":null,"hash_fragment":null}',
            ],
            [
                'opts'     => [
                    'hash_scheme'   => true,
                    'hash_userinfo' => true,
                    'hash_host'     => true,
                    'hash_port'     => true,
                    'hash_path'     => true,
                    'hash_query'    => false,
                    'hash_fragment' => false,
                ],
                'expected' => '{"hash_scheme":"https","hash_userinfo":"user:hunter2","hash_host":"subdomain.example.com","hash_port":42,"hash_path":"/path/to","hash_query":null,"hash_fragment":null}',
            ],
            [
                'opts'     => [
                    'hash_scheme'   => true,
                    'hash_userinfo' => true,
                    'hash_host'     => true,
                    'hash_port'     => true,
                    'hash_path'     => true,
                    'hash_query'    => true,
                    'hash_fragment' => false,
                ],
                'expected' => '{"hash_scheme":"https","hash_userinfo":"user:hunter2","hash_host":"subdomain.example.com","hash_port":42,"hash_path":"/path/to","hash_query":"qux=baz&zfoo=bar","hash_fragment":null}',
            ],
            [
                'opts'     => [
                    'hash_scheme'   => false,
                    'hash_userinfo' => true,
                    'hash_host'     => true,
                    'hash_port'     => true,
                    'hash_path'     => true,
                    'hash_query'    => true,
                    'hash_fragment' => true,
                ],
                'expected' => '{"hash_scheme":null,"hash_userinfo":"user:hunter2","hash_host":"subdomain.example.com","hash_port":42,"hash_path":"/path/to","hash_query":"qux=baz&zfoo=bar","hash_fragment":"anchor"}',
            ],
        ];

        foreach ($urls as $urlData) {
            yield [$url, $urlData['opts'], $urlData['expected']];
        }
    }
}