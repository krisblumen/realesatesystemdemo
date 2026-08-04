<?php

namespace Tests\Unit;

use App\Support\MexicanPhoneFormatter;
use PHPUnit\Framework\TestCase;

class MexicanPhoneFormatterTest extends TestCase
{
    public function test_formats_a_regular_number_in_groups_of_three_three_two_two(): void
    {
        $this->assertSame('442-119-09-59', MexicanPhoneFormatter::format('4421190959'));
    }

    public function test_formats_a_mexico_city_number_starting_with_55_in_groups_of_two(): void
    {
        $this->assertSame('55-10-10-10-10', MexicanPhoneFormatter::format('5510101010'));
    }

    public function test_strips_existing_formatting_before_reformatting(): void
    {
        $this->assertSame('442-119-09-59', MexicanPhoneFormatter::format('442 119 09 59'));
        $this->assertSame('442-119-09-59', MexicanPhoneFormatter::format('(442) 119-0959'));
    }

    public function test_strips_the_52_country_code_before_formatting(): void
    {
        $this->assertSame('55-10-10-10-10', MexicanPhoneFormatter::format('525510101010'));
        $this->assertSame('55-10-10-10-10', MexicanPhoneFormatter::format('+52 55 1010 1010'));
    }

    public function test_returns_the_original_value_when_it_is_not_a_recognizable_ten_digit_number(): void
    {
        $this->assertSame('123', MexicanPhoneFormatter::format('123'));
        $this->assertSame('01 800 123 4567', MexicanPhoneFormatter::format('01 800 123 4567'));
    }

    public function test_returns_null_for_null_input(): void
    {
        $this->assertNull(MexicanPhoneFormatter::format(null));
    }
}
