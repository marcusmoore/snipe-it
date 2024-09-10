<?php

use App\Models\User;

test('first name split', function () {
    $fullname = "Natalia Allanovna Romanova-O'Shostakova";
    $expected_firstname = 'Natalia';
    $expected_lastname = "Allanovna Romanova-O'Shostakova";
    $user = User::generateFormattedNameFromFullName($fullname, 'firstname');
    expect($user['first_name'])->toEqual($expected_firstname);
    expect($user['last_name'])->toEqual($expected_lastname);
});

test('first name', function () {
    $fullname = "Natalia Allanovna Romanova-O'Shostakova";
    $expected_username = 'natalia';
    $user = User::generateFormattedNameFromFullName($fullname, 'firstname');
    expect($user['username'])->toEqual($expected_username);
});

test('first name dot last name', function () {
    $fullname = "Natalia Allanovna Romanova-O'Shostakova";
    $expected_username = 'natalia.allanovna-romanova-oshostakova';
    $user = User::generateFormattedNameFromFullName($fullname, 'firstname.lastname');
    expect($user['username'])->toEqual($expected_username);
});

test('last name first initial', function () {
    $fullname = "Natalia Allanovna Romanova-O'Shostakova";
    $expected_username = 'allanovna-romanova-oshostakovan';
    $user = User::generateFormattedNameFromFullName($fullname, 'lastnamefirstinitial');
    expect($user['username'])->toEqual($expected_username);
});

test('first initial last name', function () {
    $fullname = "Natalia Allanovna Romanova-O'Shostakova";
    $expected_username = 'nallanovna-romanova-oshostakova';
    $user = User::generateFormattedNameFromFullName($fullname, 'filastname');
    expect($user['username'])->toEqual($expected_username);
});

test('first initial underscore last name', function () {
    $fullname = "Natalia Allanovna Romanova-O'Shostakova";
    $expected_username = 'nallanovna-romanova-oshostakova';
    $user = User::generateFormattedNameFromFullName($fullname, 'firstinitial_lastname');
    expect($user['username'])->toEqual($expected_username);
});

test('single name', function () {
    $fullname = 'Natalia';
    $expected_username = 'natalia';
    $user = User::generateFormattedNameFromFullName($fullname, 'firstname_lastname',);
    expect($user['username'])->toEqual($expected_username);
});

function firstInitialDotLastname()
{
    $fullname = "Natalia Allanovna Romanova-O'Shostakova";
    $expected_username = 'n.allanovnaromanovaoshostakova';
    $user = User::generateFormattedNameFromFullName($fullname, 'firstinitial.lastname');
    expect($user['username'])->toEqual($expected_username);
}

function lastNameUnderscoreFirstInitial()
{
    $fullname = "Natalia Allanovna Romanova-O'Shostakova";
    $expected_username = 'allanovnaromanovaoshostakova_n';
    $user = User::generateFormattedNameFromFullName($fullname, 'lastname_firstinitial');
    expect($user['username'])->toEqual($expected_username);
}

function firstNameLastName()
{
    $fullname = "Natalia Allanovna Romanova-O'Shostakova";
    $expected_username = 'nataliaallanovnaromanovaoshostakova';
    $user = User::generateFormattedNameFromFullName($fullname, 'firstnamelastname');
    expect($user['username'])->toEqual($expected_username);
}

function firstNameLastInitial()
{
    $fullname = "Natalia Allanovna Romanova-O'Shostakova";
    $expected_username = 'nataliaa';
    $user = User::generateFormattedNameFromFullName($fullname, 'firstnamelastinitial');
    expect($user['username'])->toEqual($expected_username);
}
