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

test('first name email', function () {
    $fullname = "Natalia Allanovna Romanova-O'Shostakova";
    $expected_email = 'natalia@example.com';
    $user = User::generateFormattedNameFromFullName($fullname, 'firstname');
    expect($user['username'] . '@example.com')->toEqual($expected_email);
});

test('last name', function () {
    $fullname = "Natalia Allanovna Romanova-O'Shostakova";
    $expected_username = 'allanovna-romanova-oshostakova';
    $user = User::generateFormattedNameFromFullName($fullname, 'lastname');
    expect($user['username'])->toEqual($expected_username);
});

test('last name email', function () {
    $fullname = "Natalia Allanovna Romanova-O'Shostakova";
    $expected_username = 'allanovna-romanova-oshostakova@example.com';
    $user = User::generateFormattedNameFromFullName($fullname, 'lastname');
    expect($user['username'] . '@example.com')->toEqual($expected_username);
});

test('first name dot last name', function () {
    $fullname = "Natalia Allanovna Romanova-O'Shostakova";
    $expected_username = 'natalia.allanovna-romanova-oshostakova';
    $user = User::generateFormattedNameFromFullName($fullname, 'firstname.lastname');
    expect($user['username'])->toEqual($expected_username);
});

test('first name dot last name email', function () {
    $fullname = "Natalia Allanovna Romanova-O'Shostakova";
    $expected_email = 'natalia.allanovna-romanova-oshostakova@example.com';
    $user = User::generateFormattedNameFromFullName($fullname, 'firstname.lastname');
    expect($user['username'] . '@example.com')->toEqual($expected_email);
});

test('last name first initial', function () {
    $fullname = "Natalia Allanovna Romanova-O'Shostakova";
    $expected_username = 'allanovna-romanova-oshostakovan';
    $user = User::generateFormattedNameFromFullName($fullname, 'lastnamefirstinitial');
    expect($user['username'])->toEqual($expected_username);
});

test('last name first initial email', function () {
    $fullname = "Natalia Allanovna Romanova-O'Shostakova";
    $expected_email = 'allanovna-romanova-oshostakovan@example.com';
    $user = User::generateFormattedNameFromFullName($fullname, 'lastnamefirstinitial');
    expect($user['username'] . '@example.com')->toEqual($expected_email);
});

test('first initial last name', function () {
    $fullname = "Natalia Allanovna Romanova-O'Shostakova";
    $expected_username = 'nallanovna-romanova-oshostakova';
    $user = User::generateFormattedNameFromFullName($fullname, 'filastname');
    expect($user['username'])->toEqual($expected_username);
});

test('first initial last name email', function () {
    $fullname = "Natalia Allanovna Romanova-O'Shostakova";
    $expected_email = 'nallanovna-romanova-oshostakova@example.com';
    $user = User::generateFormattedNameFromFullName($fullname, 'filastname');
    expect($user['username'] . '@example.com')->toEqual($expected_email);
});

test('first initial underscore last name', function () {
    $fullname = "Natalia Allanovna Romanova-O'Shostakova";
    $expected_username = 'nallanovna-romanova-oshostakova';
    $user = User::generateFormattedNameFromFullName($fullname, 'firstinitial_lastname');
    expect($user['username'])->toEqual($expected_username);
});

test('first initial underscore last name email', function () {
    $fullname = "Natalia Allanovna Romanova-O'Shostakova";
    $expected_email = 'nallanovna-romanova-oshostakova@example.com';
    $user = User::generateFormattedNameFromFullName($fullname, 'firstinitial_lastname');
    expect($user['username'] . '@example.com')->toEqual($expected_email);
});

test('single name', function () {
    $fullname = 'Natalia';
    $expected_username = 'natalia';
    $user = User::generateFormattedNameFromFullName($fullname, 'firstname_lastname',);
    expect($user['username'])->toEqual($expected_username);
});

test('single name email', function () {
    $fullname = 'Natalia';
    $expected_email = 'natalia@example.com';
    $user = User::generateFormattedNameFromFullName($fullname, 'firstname_lastname',);
    expect($user['username'] . '@example.com')->toEqual($expected_email);
});

test('first initial dot lastname', function () {
    $fullname = "Natalia Allanovna Romanova-O'Shostakova";
    $expected_username = 'nallanovna-romanova-oshostakova';
    $user = User::generateFormattedNameFromFullName($fullname, 'firstinitial.lastname');
    expect($user['username'])->toEqual($expected_username);
});

test('first initial dot lastname email', function () {
    $fullname = "Natalia Allanovna Romanova-O'Shostakova";
    $expected_email = 'nallanovna-romanova-oshostakova@example.com';
    $user = User::generateFormattedNameFromFullName($fullname, 'firstinitial.lastname');
    expect($user['username'] . '@example.com')->toEqual($expected_email);
});

test('last name dot first initial', function () {
    $fullname = "Natalia Allanovna Romanova-O'Shostakova";
    $expected_username = 'allanovna-romanova-oshostakova.n';
    $user = User::generateFormattedNameFromFullName($fullname, 'lastname.firstinitial');
    expect($user['username'])->toEqual($expected_username);
});

test('last name dot first initial email', function () {
    $fullname = "Natalia Allanovna Romanova-O'Shostakova";
    $expected_email = 'allanovna-romanova-oshostakova.n@example.com';
    $user = User::generateFormattedNameFromFullName($fullname, 'lastname.firstinitial');
    expect($user['username'] . '@example.com')->toEqual($expected_email);
});

test('last name underscore first initial', function () {
    $fullname = "Natalia Allanovna Romanova-O'Shostakova";
    $expected_username = 'allanovna-romanova-oshostakova_n';
    $user = User::generateFormattedNameFromFullName($fullname, 'lastname_firstinitial');
    expect($user['username'])->toEqual($expected_username);
});

test('last name underscore first initial email', function () {
    $fullname = "Natalia Allanovna Romanova-O'Shostakova";
    $expected_email = 'allanovna-romanova-oshostakova_n@example.com';
    $user = User::generateFormattedNameFromFullName($fullname, 'lastname_firstinitial');
    expect($user['username'] . '@example.com')->toEqual($expected_email);
});

test('first name last name', function () {
    $fullname = "Natalia Allanovna Romanova-O'Shostakova";
    $expected_username = 'nataliaallanovna-romanova-oshostakova';
    $user = User::generateFormattedNameFromFullName($fullname, 'firstnamelastname');
    expect($user['username'])->toEqual($expected_username);
});

test('first name last name email', function () {
    $fullname = "Natalia Allanovna Romanova-O'Shostakova";
    $expected_email = 'nataliaallanovna-romanova-oshostakova@example.com';
    $user = User::generateFormattedNameFromFullName($fullname, 'firstnamelastname');
    expect($user['username'] . '@example.com')->toEqual($expected_email);
});

test('first name last initial', function () {
    $fullname = "Natalia Allanovna Romanova-O'Shostakova";
    $expected_username = 'nataliaa';
    $user = User::generateFormattedNameFromFullName($fullname, 'firstnamelastinitial');
    expect($user['username'])->toEqual($expected_username);
});

test('first name last initial email', function () {
    $fullname = "Natalia Allanovna Romanova-O'Shostakova";
    $expected_email = 'nataliaa@example.com';
    $user = User::generateFormattedNameFromFullName($fullname, 'firstnamelastinitial');
    expect($user['username'] . '@example.com')->toEqual($expected_email);
});
