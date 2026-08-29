<?php

namespace FFans\BbcodeStudio\Tests\unit;

use FFans\BbcodeStudio\Formatter\BbcodeUsageInspector;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BbcodeUsageInspectorTest extends TestCase
{
    #[Test]
    public function it_detects_attribute_types_optionality_and_choices(): void
    {
        $attributes = BbcodeUsageInspector::attributes(
            '[box title={SIMPLETEXT?} level={INT} mode={CHOICE=info,warning,error;optional}]{TEXT}[/box]'
        );

        $this->assertSame([
            [
                'name' => 'title',
                'type' => 'SIMPLETEXT',
                'optional' => true,
                'choices' => [],
            ],
            [
                'name' => 'level',
                'type' => 'INT',
                'optional' => false,
                'choices' => [],
            ],
            [
                'name' => 'mode',
                'type' => 'CHOICE',
                'optional' => true,
                'choices' => ['info', 'warning', 'error'],
            ],
        ], $attributes);
    }

    #[Test]
    public function it_supports_numbered_tokens_and_choice_question_mark_shorthand(): void
    {
        $attributes = BbcodeUsageInspector::attributes(
            '[box first={SIMPLETEXT1} second={CHOICE2=a,b?}]{TEXT}[/box]'
        );

        $this->assertSame('SIMPLETEXT', $attributes[0]['type']);
        $this->assertSame('CHOICE', $attributes[1]['type']);
        $this->assertTrue($attributes[1]['optional']);
        $this->assertSame(['a', 'b'], $attributes[1]['choices']);
    }

    #[Test]
    public function it_only_reads_attributes_from_the_opening_tag(): void
    {
        $attributes = BbcodeUsageInspector::attributes(
            '[box pattern={REGEXP=/^[a-z\]]+$/}]{TEXT}[/box] ignored={CHOICE=x,y}'
        );

        $this->assertSame(['pattern'], array_column($attributes, 'name'));
    }
}
