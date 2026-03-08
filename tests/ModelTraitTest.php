<?php

namespace AnourValar\EloquentValidation\Tests;

use AnourValar\EloquentValidation\Tests\Models\Post;
use Tests\TestCase;

class ModelTraitTest extends AbstractSuite
{
    use \Illuminate\Foundation\Testing\DatabaseTransactions;

    /**
     * @return void
     */
    public function test_jsonNested_root_nullable()
    {
        $model = new Post()->mergeJsonNested(['data' => ['types' => ['*' => 'int']]]);

        $this->assertNull($model->forceFill(['data' => []])->drive_license_grid);
        $this->assertNull($model->forceFill(['data' => ''])->drive_license_grid);
        $this->assertNull($model->forceFill(['data' => ' '])->drive_license_grid);
    }

    /**
     * @return void
     */
    public function test_jsonNested_nullable1()
    {
        $model = new Post()->mergeCasts(['foo' => 'json'])->mergeJsonNested(['foo' => ['nullable' => ['*']]]);

        $this->assertSame([], $model->forceFill(['foo' => []])->foo);
        $this->assertSame('', $model->forceFill(['foo' => ''])->foo);
        $this->assertSame(' ', $model->forceFill(['foo' => ' '])->foo);

        $this->assertSame(['bar' => null], $model->forceFill(['foo' => ['bar' => []]])->foo);
        $this->assertSame(['bar' => null], $model->forceFill(['foo' => ['bar' => '']])->foo);
        $this->assertSame(['bar' => null], $model->forceFill(['foo' => ['bar' => ' ']])->foo);

        $this->assertSame(['bar' => ['test' => null]], $model->forceFill(['foo' => ['bar' => ['test' => []]]])->foo);
        $this->assertSame(['bar' => ['test' => null]], $model->forceFill(['foo' => ['bar' => ['test' => '']]])->foo);
        $this->assertSame(['bar' => ['test' => null]], $model->forceFill(['foo' => ['bar' => ['test' => ' ']]])->foo);

        $this->assertSame(
            [
                'a' => null,
                'b' => 'b',
                'c' => [
                    'd' => null,
                    'e' => null,
                    'f' => null,
                    'g' => 'g',
                    'j' => [
                        'k' => null,
                        'l' => 'l',
                    ],
                ],
            ],
            $model->forceFill(['foo' => [
                'a' => '',
                'b' => 'b',
                'c' => [
                    'd' => null,
                    'e' => [],
                    'f' => '',
                    'g' => 'g',
                    'j' => [
                        'k' => '',
                        'l' => 'l',
                    ],
                ],
            ]])->foo
        );
    }

    /**
     * @return void
     */
    public function test_jsonNested_nullable2()
    {
        $model = new Post()->mergeCasts(['foo' => 'json'])->mergeJsonNested(['foo' => [
            'nullable' => ['$.a', '$.*.*'],
        ]]);

        $this->assertSame(
            [
                'a' => null,
                'b' => '',
                'c' => [
                    'd' => null,
                    'e' => null,
                    'f' => [
                        'g' => '',
                    ],
                ],
            ],
            $model->forceFill(['foo' => [
                'a' => '',
                'b' => '',
                'c' => [
                    'd' => [],
                    'e' => '',
                    'f' => [
                        'g' => '',
                    ],
                ],
            ]])->foo
        );

        $this->assertSame([], $model->forceFill(['foo' => []])->foo);
    }

    /**
     * @return void
     */
    public function test_jsonNested_nullable3()
    {
        $model = new Post()->mergeCasts(['foo' => 'json'])->mergeJsonNested(['foo' => ['nullable' => ['$']]]);

        $this->assertSame([], $model->forceFill(['foo' => []])->foo);
        $this->assertSame('', $model->forceFill(['foo' => ''])->foo);
        $this->assertSame(' ', $model->forceFill(['foo' => ' '])->foo);
    }

    /**
     * @return void
     */
    public function test_jsonNested_types1()
    {
        $model = new Post()
            ->mergeCasts(['foo' => 'json'])
            ->mergeJsonNested(['foo' => [
                'types' => [
                    '$.a' => 'int',
                    '$.b' => '?int',
                    '$.c.d' => 'int',
                    '$.c.e.f' => 'string',
                    '$.c.e.g.h' => 'int',

                    '$.n1.n2.n3.n4.n5' => 'int',

                    '$.k1.*' => 'string',

                    '$.m1.*.m3' => 'int',

                    '$.*.*.j1' => 'string',

                    '$.g.*' => 'int',
                ],
            ]])
            ->forceFill(['foo' => [
                'a' => null,
                'b' => null,
                'c' => [
                    'd' => null,
                    'e' => [
                        'f' => null,
                        'g' => ['h' => null],
                    ],
                ],
                'd' => null,
                'e' => null,
                'f' => null,

                'n1' => ['n2' => ['n3' => ['n4' => ['a' => null, 'd' => null, 'n5' => null]]]],
                'n2' => null,
                'n3' => null,
                'n4' => null,
                'n5' => null,

                'k1' => ['k2' => null, 'k3' => null],
                'k2' => null,
                'k3' => null,

                'm1' => [
                    'a' => ['m3' => null],
                    'b' => ['m4' => null],
                    'c' => ['m3' => null],
                ],

                'j01' => [
                    'a' => ['j1' => null],
                    'b' => ['j2' => null],
                ],
                'j02' => [
                    'j1' => null,
                ],

                'g' => ['1', '2'],
        ]]);

        $this->assertSame(
            [
                'a' => 0,
                'b' => null,
                'c' => [
                    'd' => 0,
                    'e' => [
                        'f' => '',
                        'g' => ['h' => 0],
                    ],
                ],
                'd' => null,
                'e' => null,
                'f' => null,

                'n1' => ['n2' => ['n3' => ['n4' => ['a' => null, 'd' => null, 'n5' => 0]]]],
                'n2' => null,
                'n3' => null,
                'n4' => null,
                'n5' => null,

                'k1' => ['k2' => '', 'k3' => ''],
                'k2' => null,
                'k3' => null,

                'm1' => [
                    'a' => ['m3' => 0],
                    'b' => ['m4' => null],
                    'c' => ['m3' => 0],
                ],

                'j01' => [
                    'a' => ['j1' => ''],
                    'b' => ['j2' => null],
                ],
                'j02' => [
                    'j1' => null,
                ],

                'g' => [1, 2],
            ],
            $model->foo,
        );
    }

    /**
     * @return void
     */
    public function test_jsonNested_types2()
    {
        $model = new Post()
            ->mergeCasts(['foo' => 'json'])
            ->mergeJsonNested(['foo' => ['types' => ['$.a' => 'string', '*' => 'int', '$.b' => 'string']]])
            ->forceFill(['foo' => [
                'a' => null,
                'b' => '',
                'c' => [
                    'd' => 1,
                    'e' => [
                        'f' => '',
                        'g' => ['h' => ' 2 '],
                    ],
                ],
        ]]);

        $this->assertSame(
            [
                'a' => 0,
                'b' => '0',
                'c' => [
                    'd' => 1,
                    'e' => [
                        'f' => 0,
                        'g' => ['h' => 2],
                    ],
                ],
            ],
            $model->foo,
        );
    }

    /**
     * @return void
     */
    public function test_jsonNested_types3()
    {
        $model = new Post()
            ->mergeCasts(['foo' => 'json'])
            ->mergeJsonNested(['foo' => ['types' => ['$.1' => 'string', '$.3' => 'string', '$.5.2' => 'integer']]])
            ->forceFill(['foo' => [
                '1' => 1,
                '2' => 2,
                '3' => 3,
                '4' => 4,
                '5' => ['1' => '1', '2' => '2', '3' => '3'],
        ]]);

        $this->assertSame(
            [
                '1' => '1',
                '2' => 2,
                '3' => '3',
                '4' => 4,
                '5' => ['1' => '1', '2' => 2, '3' => '3'],
            ],
            $model->foo,
        );
    }

    /**
     * @return void
     */
    public function test_jsonNested_types4()
    {
        $model = new Post()
            ->mergeCasts(['foo' => 'json'])
            ->mergeJsonNested(['foo' => ['types' => ['$.*' => 'datetime']]])
            ->forceFill(['foo' => [
                0 => '2024-01-01 12:00:00+03:00',
                1 => '2024-01-01 09:00:00',
                2 => '2024-10-09T14:03:01.472005Z',
                3 => '1728482596',
                4 => 1728482596,
                5 => ['2024-01-01 12:00:00+03:00'],
                6 => null,
                7 => false,
                8 => true,
                9 => 0,
                10 => '0',
                11 => '1',
                12 => 1,
                13 => 1.5,
                14 => 'bar',
                15 => '2025-01-01',
                16 => '2025-01-01 13:00',
                17 => '2024-01-02T16:00:00+03:00',
                18 => '09.10.2024',
                19 => '',
        ]]);

        $this->assertSame(
            [
                0 => '2024-01-01 09:00:00',
                1 => '2024-01-01 09:00:00',
                2 => '2024-10-09 14:03:01',
                3 => '1728482596',
                4 => 1728482596,
                5 => ['2024-01-01 12:00:00+03:00'],
                6 => null,
                7 => false,
                8 => true,
                9 => 0,
                10 => '0',
                11 => '1',
                12 => 1,
                13 => 1.5,
                14 => 'bar',
                15 => '2025-01-01 00:00:00',
                16 => '2025-01-01 13:00:00',
                17 => '2024-01-02 13:00:00',
                18 => '2024-10-09 00:00:00',
                19 => '',
            ],
            $model->foo,
        );

        $model = new Post()
            ->mergeCasts(['foo' => 'json'])
            ->mergeJsonNested(['foo' => ['types' => ['$.*' => 'datetime:Y-m-d']]])
            ->forceFill(['foo' => ['2024-01-01 12:00:00+03:00']]);
        $this->assertSame(['2024-01-01'], $model->foo);
    }

    /**
     * @return void
     */
    public function test_jsonNested_sorts()
    {
        $model = new Post()
            ->mergeCasts(['foo' => 'json'])
            ->mergeJsonNested(['foo' => [
                'sorts' => [
                    '$.a', '$.c.d', '$.e.*',
                ],
            ]])
            ->forceFill(['foo' => [
                'a' => [2, 1],
                'b' => [2, 1],
                'c' => [
                    'd' => [2, 1],
                ],
                'd' => [2, 1],
                'e' => [
                    'f' => [2, 1],
                    'g' => [2, 1],
                ],
        ]]);

        $this->assertSame(
            [
                'a' => [1 => 1, 0 => 2],
                'b' => [2, 1],
                'c' => [
                    'd' => [1 => 1, 0 => 2],
                ],
                'd' => [2, 1],
                'e' => [
                    'f' => [1 => 1, 0 => 2],
                    'g' => [1 => 1, 0 => 2],
                ],
            ],
            $model->foo,
        );
    }

    /**
     * @return void
     */
    public function test_jsonNested_lists()
    {
        // Simple 1
        $model = new Post()
            ->mergeCasts(['foo' => 'json'])
            ->mergeJsonNested(['foo' => [
                'lists' => [
                    '$',
                ],
            ]])
            ->forceFill(['foo' => [
                'a' => 2,
                'b' => 1,
                'c' => ['foo' => 3, 'bar' => 4],
        ]]);

        $this->assertSame(
            [
                2, 1, ['foo' => 3, 'bar' => 4],
            ],
            $model->foo,
        );

        // Simple 2
        $model = new Post()
            ->mergeCasts(['foo' => 'json'])
            ->mergeJsonNested(['foo' => [
                'lists' => [
                    '$.c',
                    '$.d',
                ],
            ]])
            ->forceFill(['foo' => [
                'a' => 2,
                'b' => 1,
                'c' => ['foo' => 3, 'bar' => 4],
                'd' => [1 => 3, 0 => 4],
        ]]);

        $this->assertSame(
            [
                'a' => 2,
                'b' => 1,
                'c' => [3, 4],
                'd' => [3, 4],
            ],
            $model->foo,
        );

        // Simple 3
        $model = new Post()
            ->mergeCasts(['foo' => 'json'])
            ->mergeJsonNested(['foo' => [
                'lists' => [
                    '$.a',
                ],
            ]])
            ->forceFill(['foo' => [
                'b' => ['a' => ['foo' => 'bar']],
        ]]);

        $this->assertSame(
            [
                'b' => ['a' => ['foo' => 'bar']],
            ],
            $model->foo,
        );

        // Simple 4
        $model = new Post()
            ->mergeCasts(['foo' => 'json'])
            ->mergeJsonNested(['foo' => [
                'jsonb' => true,
                'lists' => [
                    '$',
                ],
            ]])
            ->forceFill(['foo' => [
                '-1' => ['title' => 'foo', 'email' => 'foo@example.org'],
                '-2' => ['title' => 'bar', 'email' => 'bar@example.org'],
        ]]);

        $this->assertSame(
            [
                ['email' => 'foo@example.org', 'title' => 'foo'],
                ['email' => 'bar@example.org', 'title' => 'bar'],
            ],
            $model->foo,
        );

        // Combine
        $model = new Post()
            ->mergeCasts(['foo' => 'json'])
            ->mergeJsonNested(['foo' => [
                'sorts' => [
                    '$',
                ],
                'lists' => [
                    '$',
                ],
            ]])
            ->forceFill(['foo' => [
                'a' => 2,
                'b' => 1,
                'c' => [3, 4],
        ]]);

        $this->assertSame(
            [
                1, 2, [3, 4],
            ],
            $model->foo,
        );

        // Complex 1
        $model = new Post()
            ->mergeCasts(['foo' => 'json'])
            ->mergeJsonNested(['foo' => [
                'lists' => [
                    '$',
                    '$.a',
                    '$.*.b',
                    '$.*.*.c',
                ],
            ]])
            ->forceFill(['foo' => [
                'a' => [
                    'a' => ['a' => ['foo' => 'bar'], 'b' => ['foo' => 'bar'], 'c' => ['foo' => 'bar']],
                    'b' => ['a' => ['foo' => 'bar'], 'b' => ['foo' => 'bar'], 'c' => ['foo' => 'bar']],
                    'c' => ['a' => ['foo' => 'bar'], 'b' => ['foo' => 'bar'], 'c' => ['foo' => 'bar']],
                ],
                'b' => [
                    'a' => ['a' => ['foo' => 'bar'], 'b' => ['foo' => 'bar'], 'c' => ['foo' => 'bar']],
                    'b' => ['a' => ['foo' => 'bar'], 'b' => ['foo' => 'bar'], 'c' => ['foo' => 'bar']],
                    'c' => ['a' => ['foo' => 'bar'], 'b' => ['foo' => 'bar'], 'c' => ['foo' => 'bar']],
                ],
                'c' => [
                    'a' => ['a' => ['foo' => 'bar'], 'b' => ['foo' => 'bar'], 'c' => ['foo' => 'bar']],
                    'b' => ['a' => ['foo' => 'bar'], 'b' => ['foo' => 'bar'], 'c' => ['foo' => 'bar']],
                    'c' => ['a' => ['foo' => 'bar'], 'b' => ['foo' => 'bar'], 'c' => ['foo' => 'bar']],
                ],
        ]]);

        $this->assertSame(
            [
                [
                    ['a' => ['foo' => 'bar'], 'b' => ['foo' => 'bar'], 'c' => ['bar']],
                    [['foo' => 'bar'], ['foo' => 'bar'], ['bar']],
                    ['a' => ['foo' => 'bar'], 'b' => ['foo' => 'bar'], 'c' => ['bar']],
                ],
                [
                    'a' => ['a' => ['foo' => 'bar'], 'b' => ['foo' => 'bar'], 'c' => ['bar']],
                    'b' => [['foo' => 'bar'], ['foo' => 'bar'], ['bar']],
                    'c' => ['a' => ['foo' => 'bar'], 'b' => ['foo' => 'bar'], 'c' => ['bar']],
                ],
                [
                    'a' => ['a' => ['foo' => 'bar'], 'b' => ['foo' => 'bar'], 'c' => ['bar']],
                    'b' => [['foo' => 'bar'], ['foo' => 'bar'], ['bar']],
                    'c' => ['a' => ['foo' => 'bar'], 'b' => ['foo' => 'bar'], 'c' => ['bar']],
                ],
            ],
            $model->foo,
        );

        // Complex 2
        $model = new Post()
            ->mergeCasts(['foo' => 'json'])
            ->mergeJsonNested(['foo' => [
                'lists' => [
                    '$.a',
                    '$.*.b',
                    '$.a.b.c',
                    '$.b.*.c',
                    '$.c.a.c',
                ],
            ]])
            ->forceFill(['foo' => [
                'a' => [
                    'a' => ['a' => ['foo' => 'bar'], 'b' => ['foo' => 'bar'], 'c' => ['foo' => 'bar']],
                    'b' => ['a' => ['foo' => 'bar'], 'b' => ['foo' => 'bar'], 'c' => ['foo' => 'bar']],
                    'c' => ['a' => ['foo' => 'bar'], 'b' => ['foo' => 'bar'], 'c' => ['foo' => 'bar']],
                ],
                'b' => [
                    'a' => ['a' => ['foo' => 'bar'], 'b' => ['foo' => 'bar'], 'c' => ['foo' => 'bar']],
                    'b' => ['a' => ['foo' => 'bar'], 'b' => ['foo' => 'bar'], 'c' => ['foo' => 'bar']],
                    'c' => ['a' => ['foo' => 'bar'], 'b' => ['foo' => 'bar'], 'c' => ['foo' => 'bar']],
                ],
                'c' => [
                    'a' => ['a' => ['foo' => 'bar'], 'b' => ['foo' => 'bar'], 'c' => ['foo' => 'bar']],
                    'b' => ['a' => ['foo' => 'bar'], 'b' => ['foo' => 'bar'], 'c' => ['foo' => 'bar']],
                    'c' => ['a' => ['foo' => 'bar'], 'b' => ['foo' => 'bar'], 'c' => ['foo' => 'bar']],
                ],
        ]]);

        $this->assertSame(
            [
                'a' => [
                    ['a' => ['foo' => 'bar'], 'b' => ['foo' => 'bar'], 'c' => ['foo' => 'bar']],
                    [['foo' => 'bar'], ['foo' => 'bar'], ['bar']],
                    ['a' => ['foo' => 'bar'], 'b' => ['foo' => 'bar'], 'c' => ['foo' => 'bar']],
                ],
                'b' => [
                    'a' => ['a' => ['foo' => 'bar'], 'b' => ['foo' => 'bar'], 'c' => ['bar']],
                    'b' => [['foo' => 'bar'], ['foo' => 'bar'], ['bar']],
                    'c' => ['a' => ['foo' => 'bar'], 'b' => ['foo' => 'bar'], 'c' => ['bar']],
                ],
                'c' => [
                    'a' => ['a' => ['foo' => 'bar'], 'b' => ['foo' => 'bar'], 'c' => ['bar']],
                    'b' => [['foo' => 'bar'], ['foo' => 'bar'], ['foo' => 'bar']],
                    'c' => ['a' => ['foo' => 'bar'], 'b' => ['foo' => 'bar'], 'c' => ['foo' => 'bar']],
                ],
            ],
            $model->foo,
        );
    }

    /**
     * @return void
     */
    public function test_jsonNested_purges()
    {
        // Simple 1
        $model = new Post()
            ->mergeCasts(['foo' => 'json'])
            ->mergeJsonNested(['foo' => [
                'purges' => [
                    '$.a',
                    '$.b',
                    '$.a.b.c',
                    '$.c.d',
                ],
            ]])
            ->forceFill(['foo' => [
                'a' => ['b' => null],
                'c' => ['d' => null],
        ]]);

        $this->assertSame(
            [
                'a' => ['b' => null],
                'c' => [],
            ],
            $model->foo,
        );

        // Simple 2
        $model = new Post()
            ->mergeCasts(['foo' => 'json'])
            ->mergeJsonNested(['foo' => [
                'nullable' => ['*'],
                'purges' => [
                    '$.a.*',
                    '$.*.d',
                    '$.e.f',
                ],
            ]])
            ->forceFill(['foo' => [
                'a' => ['b' => null],
                'c' => ['d' => ''],
                'e' => ['f' => 'foo'],
        ]]);

        $this->assertSame(
            [
                'a' => null,
                'c' => null,
                'e' => ['f' => 'foo'],
            ],
            $model->foo,
        );

        // Simple 3
        $model = new Post()
            ->mergeCasts(['foo' => 'json'])
            ->mergeJsonNested(['foo' => [
                'purges' => [
                    '$.*',
                ],
            ]])
            ->forceFill(['foo' => [
                'a' => null,
                'b' => null,
                'c' => 'foo',
                'd' => [],
        ]]);

        $this->assertSame(
            [
                'c' => 'foo',
                'd' => [],
            ],
            $model->foo,
        );

        // Simple 4
        $model = new Post()
            ->mergeCasts(['foo' => 'json'])
            ->mergeJsonNested(['foo' => [
                'purges' => [
                    '*',
                ],
                'types' => ['*' => 'string'],
            ]])
            ->forceFill(['foo' => [
                'a' => null,
                'b' => null,
                'c' => 'foo',
                'd' => [
                    'e' => null,
                    'f' => 'bar',
                    'g' => [
                        'h' => 'baz',
                        'k' => null,
                    ],
                ],
        ]]);

        $this->assertSame(
            [
                'c' => 'foo',
                'd' => [
                    'f' => 'bar',
                    'g' => [
                        'h' => 'baz',
                    ],
                ],
            ],
            $model->foo,
        );

        // Simple 5
        $model = new Post()
            ->mergeCasts(['foo' => 'json'])
            ->mergeJsonNested(['foo' => [
                'purges' => [
                    '*',
                ],
            ]])
            ->forceFill(['foo' => null]);

        $this->assertNull($model->foo);

        // Complex
        $model = new Post()
            ->mergeCasts(['foo' => 'json'])
            ->mergeJsonNested(['foo' => [
                'purges' => [
                    '$.a1.b1.c1',
                    '$.a2.b2.c2',
                    '$.a3.*.c3',
                    '$.a4.b4.*',
                ],
            ]])
            ->forceFill(['foo' => [
                'a1' => ['a1' => null, 'b1' => null, 'c1' => null],
                'a2' => [
                    'a2' => ['a2' => null, 'b2' => null, 'c2' => null],
                    'b2' => ['a2' => null, 'b2' => null, 'c2' => null],
                    'c2' => ['a2' => null, 'b2' => null, 'c2' => null],
                ],
                'a3' => [
                    'a3' => ['a3' => null, 'b3' => null, 'c3' => null],
                    'b3' => ['a3' => null, 'b3' => null, 'c3' => null],
                    'c3' => ['a3' => null, 'b3' => null, 'c3' => null],
                ],
                'a4' => [
                    'a4' => ['a4' => null, 'b4' => null, 'c4' => null],
                    'b4' => ['a4' => null, 'b4' => null, 'c4' => null],
                    'c4' => ['a4' => null, 'b4' => null, 'c4' => null],
                ],
        ]]);

        $this->assertSame(
            [
                'a1' => ['a1' => null, 'b1' => null, 'c1' => null],
                'a2' => [
                    'a2' => ['a2' => null, 'b2' => null, 'c2' => null],
                    'b2' => ['a2' => null, 'b2' => null],
                    'c2' => ['a2' => null, 'b2' => null, 'c2' => null],
                ],
                'a3' => [
                    'a3' => ['a3' => null, 'b3' => null],
                    'b3' => ['a3' => null, 'b3' => null],
                    'c3' => ['a3' => null, 'b3' => null],
                ],
                'a4' => [
                    'a4' => ['a4' => null, 'b4' => null, 'c4' => null],
                    'b4' => [],
                    'c4' => ['a4' => null, 'b4' => null, 'c4' => null],
                ],
            ],
            $model->foo,
        );
    }

    /**
     * @return void
     */
    public function test_validationException()
    {
        try {
            throw (new \AnourValar\EloquentValidation\Exceptions\ValidationException('hello'));
        } catch (\AnourValar\EloquentValidation\Exceptions\ValidationException $e) {
            $this->assertSame($e->validator->errors()->keys(), ['error']);
        }

        try {
            throw (new \AnourValar\EloquentValidation\Exceptions\ValidationException('hello', null, 'default', 'foo.bar'));
        } catch (\AnourValar\EloquentValidation\Exceptions\ValidationException $e) {
            $this->assertSame($e->validator->errors()->keys(), ['foo.bar.error']);
        }

        try {
            throw (new \AnourValar\EloquentValidation\Exceptions\ValidationException('hello', null, 'default', ['foo', 'bar']));
        } catch (\AnourValar\EloquentValidation\Exceptions\ValidationException $e) {
            $this->assertSame($e->validator->errors()->keys(), ['foo.bar.error']);
        }

        try {
            throw (new \AnourValar\EloquentValidation\Exceptions\ValidationException('hello', null, 'default', ['foo', 'bar']))->replaceKey('foo', 'FOO');
        } catch (\AnourValar\EloquentValidation\Exceptions\ValidationException $e) {
            $this->assertSame($e->validator->errors()->keys(), ['FOO.bar.error']);
        }

        try {
            throw (new \AnourValar\EloquentValidation\Exceptions\ValidationException('hello', null, 'default', ['foo', 'bar']))->replaceKey('bar', 'BAR');
        } catch (\AnourValar\EloquentValidation\Exceptions\ValidationException $e) {
            $this->assertSame($e->validator->errors()->keys(), ['foo.BAR.error']);
        }

        try {
            throw (new \AnourValar\EloquentValidation\Exceptions\ValidationException('hello', null, 'default', ['foo', 'bar']))->replaceKey('error', 'ERROR');
        } catch (\AnourValar\EloquentValidation\Exceptions\ValidationException $e) {
            $this->assertSame($e->validator->errors()->keys(), ['foo.bar.ERROR']);
        }

        try {
            throw (new \AnourValar\EloquentValidation\Exceptions\ValidationException('hello', null, 'default', ['foo', 'bar']))->replaceKey('error', 'error');
        } catch (\AnourValar\EloquentValidation\Exceptions\ValidationException $e) {
            $this->assertSame($e->validator->errors()->keys(), ['foo.bar.error']);
        }

        try {
            throw (new \AnourValar\EloquentValidation\Exceptions\ValidationException('hello', null, 'default', ['foo', 'bar']))->replaceKey('error', null);
        } catch (\AnourValar\EloquentValidation\Exceptions\ValidationException $e) {
            $this->assertSame($e->validator->errors()->keys(), ['foo.bar.error']);
        }

        try {
            throw (new \AnourValar\EloquentValidation\Exceptions\ValidationException('hello', null, 'default', ['foo', 'bar']))->replaceKey('fo', '@@@');
        } catch (\AnourValar\EloquentValidation\Exceptions\ValidationException $e) {
            $this->assertSame($e->validator->errors()->keys(), ['foo.bar.error']);
        }

        try {
            throw (new \AnourValar\EloquentValidation\Exceptions\ValidationException('hello', null, 'default', ['foo', 'bar']))->replaceKey('oo', '@@@');
        } catch (\AnourValar\EloquentValidation\Exceptions\ValidationException $e) {
            $this->assertSame($e->validator->errors()->keys(), ['foo.bar.error']);
        }

        try {
            throw (new \AnourValar\EloquentValidation\Exceptions\ValidationException('hello', null, 'default', ['foo', 'bar']))->replaceKey('ba', '@@@');
        } catch (\AnourValar\EloquentValidation\Exceptions\ValidationException $e) {
            $this->assertSame($e->validator->errors()->keys(), ['foo.bar.error']);
        }

        try {
            throw (new \AnourValar\EloquentValidation\Exceptions\ValidationException('hello', null, 'default', ['foo', 'bar']))->replaceKey('ar', '@@@');
        } catch (\AnourValar\EloquentValidation\Exceptions\ValidationException $e) {
            $this->assertSame($e->validator->errors()->keys(), ['foo.bar.error']);
        }

        try {
            throw (new \AnourValar\EloquentValidation\Exceptions\ValidationException('hello', null, 'default', ['foo', 'bar']))->replaceKey('er', '@@@');
        } catch (\AnourValar\EloquentValidation\Exceptions\ValidationException $e) {
            $this->assertSame($e->validator->errors()->keys(), ['foo.bar.error']);
        }

        try {
            throw (new \AnourValar\EloquentValidation\Exceptions\ValidationException('hello', null, 'default', ['foo', 'bar']))->replaceKey('or', '@@@');
        } catch (\AnourValar\EloquentValidation\Exceptions\ValidationException $e) {
            $this->assertSame($e->validator->errors()->keys(), ['foo.bar.error']);
        }

        try {
            throw (new \AnourValar\EloquentValidation\Exceptions\ValidationException('hello', null, 'default', ['foo', 'bar']))->replaceKey('fo', '');
        } catch (\AnourValar\EloquentValidation\Exceptions\ValidationException $e) {
            $this->assertSame($e->validator->errors()->keys(), ['foo.bar.error']);
        }

        try {
            throw (new \AnourValar\EloquentValidation\Exceptions\ValidationException('hello', null, 'default', ['foo', 'bar']))->replaceKey('oo', '');
        } catch (\AnourValar\EloquentValidation\Exceptions\ValidationException $e) {
            $this->assertSame($e->validator->errors()->keys(), ['foo.bar.error']);
        }

        try {
            throw (new \AnourValar\EloquentValidation\Exceptions\ValidationException('hello', null, 'default', ['foo', 'bar']))->replaceKey('ba', '');
        } catch (\AnourValar\EloquentValidation\Exceptions\ValidationException $e) {
            $this->assertSame($e->validator->errors()->keys(), ['foo.bar.error']);
        }

        try {
            throw (new \AnourValar\EloquentValidation\Exceptions\ValidationException('hello', null, 'default', ['foo', 'bar']))->replaceKey('ar', '');
        } catch (\AnourValar\EloquentValidation\Exceptions\ValidationException $e) {
            $this->assertSame($e->validator->errors()->keys(), ['foo.bar.error']);
        }

        try {
            throw (new \AnourValar\EloquentValidation\Exceptions\ValidationException('hello', null, 'default', ['foo', 'bar']))->replaceKey('er', '');
        } catch (\AnourValar\EloquentValidation\Exceptions\ValidationException $e) {
            $this->assertSame($e->validator->errors()->keys(), ['foo.bar.error']);
        }

        try {
            throw (new \AnourValar\EloquentValidation\Exceptions\ValidationException('hello', null, 'default', ['foo', 'bar']))->replaceKey('or', '');
        } catch (\AnourValar\EloquentValidation\Exceptions\ValidationException $e) {
            $this->assertSame($e->validator->errors()->keys(), ['foo.bar.error']);
        }

        try {
            throw (new \AnourValar\EloquentValidation\Exceptions\ValidationException('hello', null, 'default', ['foo', 'bar']))->replaceKey('foo', '');
        } catch (\AnourValar\EloquentValidation\Exceptions\ValidationException $e) {
            $this->assertSame($e->validator->errors()->keys(), ['bar.error']);
        }

        try {
            throw (new \AnourValar\EloquentValidation\Exceptions\ValidationException('hello', null, 'default', ['foo', 'bar']))->replaceKey('bar', '');
        } catch (\AnourValar\EloquentValidation\Exceptions\ValidationException $e) {
            $this->assertSame($e->validator->errors()->keys(), ['foo.error']);
        }

        try {
            throw (new \AnourValar\EloquentValidation\Exceptions\ValidationException('hello', null, 'default', ['foo', 'bar']))->replaceKey('error', '');
        } catch (\AnourValar\EloquentValidation\Exceptions\ValidationException $e) {
            $this->assertSame($e->validator->errors()->keys(), ['foo.bar']);
        }
    }

    /**
     * @return void
     */
    public function test_validate_general1()
    {
        $model = new Post();
        $model->data = ['foo']; /** @phpstan-ignore-line */
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $model->validate();
    }

    /**
     * @return void
     */
    public function test_validate_general2()
    {
        $this->partialMock(Post::class, function ($mock) {
            $mock->shouldReceive('saveRules')->twice()->andReturn([
                'one' => ['required', 'string'],
                'two' => ['required', 'integer'],
            ]);

            $mock->shouldReceive('saveAfterValidation')->once();
        });

        $model = \App::make(Post::class);
        $model->withCasts(['one' => 'string', 'two' => 'integer']);

        $this->assertValidationSuccess($model->forceFill(['one' => 1, 'two' => '2']));
        $this->assertValidationFailed($model->forceFill(['one' => [1], 'two' => ['2']]), ['one', 'two']);
    }

    /**
     * @return void
     */
    public function test_validate_general3()
    {
        $this->partialMock(Post::class, function ($mock) {
            $mock->shouldReceive('saveRules')->times(3)->andReturn([
                'one' => ['required', 'integer', 'min:1000', 'max:1000000'],
            ]);

            $mock->shouldReceive('saveAfterValidation')->once();
        });

        $model = \App::make(Post::class);
        $model->withCasts(['one' => 'integer']);

        $this->assertValidationSuccess($model->forceFill(['one' => 1000000]));

        $this->assertValidationFailed(
            $model->forceFill(['one' => 1000001]),
            ['one'],
            trans('validation.max.numeric', ['attribute' => 'one', 'max' => 1000000])
        );

        $this->assertValidationFailed(
            $model->forceFill(['one' => 999]),
            ['one'],
            trans('validation.min.numeric', ['attribute' => 'one', 'min' => '1000'])
        );
    }

    /**
     * @return void
     */
    public function test_validate_general4()
    {
        $this->partialMock(Post::class, function ($mock) {
            $mock->shouldReceive('saveRules')->times(3)->andReturn([
                'one' => ['required', 'integer', 'min:1000,1 000', 'max:1000000,1 000 000'],
            ]);

            $mock->shouldReceive('saveAfterValidation')->once();
        });

        $model = \App::make(Post::class);
        $model->withCasts(['one' => 'integer']);

        $this->assertValidationSuccess($model->forceFill(['one' => '1000000']));
        $this->assertValidationFailed(
            $model->forceFill(['one' => 1000001]),
            ['one'],
            trans('validation.max.numeric', ['attribute' => 'one', 'max' => '1 000 000'])
        );

        $this->assertValidationFailed(
            $model->forceFill(['one' => 999]),
            ['one'],
            trans('validation.min.numeric', ['attribute' => 'one', 'min' => '1 000'])
        );
    }

    /**
     * @return void
     */
    public function test_validate_general5()
    {
        $model = new Post();

        $this->expectException(\LogicException::class);
        $model->foo = 'bar';
    }
}
