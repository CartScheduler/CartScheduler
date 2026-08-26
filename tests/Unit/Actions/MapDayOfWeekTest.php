<?php

use App\Actions\MapDayOfWeek;

test('convert day of week to integer', function () {
    $mapDayOfWeek = new MapDayOfWeek;

    expect($mapDayOfWeek->toInteger('SUN'))->toEqual(0);
    expect($mapDayOfWeek->toInteger('MON'))->toEqual(1);
    expect($mapDayOfWeek->toInteger('TUE'))->toEqual(2);
    expect($mapDayOfWeek->toInteger('WED'))->toEqual(3);
    expect($mapDayOfWeek->toInteger('THU'))->toEqual(4);
    expect($mapDayOfWeek->toInteger('FRI'))->toEqual(5);
    expect($mapDayOfWeek->toInteger('SAT'))->toEqual(6);
});

test('throw an exception for invalid day of week', function () {
    $mapDayOfWeek = new MapDayOfWeek;

    $this->expectException(InvalidArgumentException::class);
    $mapDayOfWeek->toInteger('INVALID_DAY');
});

test('lengthen', function () {
    $mapDayOfWeek = new MapDayOfWeek;

    expect($mapDayOfWeek->lengthen('SUN'))->toEqual('Sunday');
    expect($mapDayOfWeek->lengthen('MON'))->toEqual('Monday');
    expect($mapDayOfWeek->lengthen('TUE'))->toEqual('Tuesday');
    expect($mapDayOfWeek->lengthen('WED'))->toEqual('Wednesday');
    expect($mapDayOfWeek->lengthen('THU'))->toEqual('Thursday');
    expect($mapDayOfWeek->lengthen('FRI'))->toEqual('Friday');
    expect($mapDayOfWeek->lengthen('SAT'))->toEqual('Saturday');
});

test('lengthen with invalid day of week', function () {
    $mapDayOfWeek = new MapDayOfWeek;

    $this->expectException(InvalidArgumentException::class);
    $mapDayOfWeek->lengthen('INVALID');
});
