<?php

use App\Models\Setting;
use App\Models\Ldap;

uses(\phpmock\phpunit\PHPMock::class);

test('connect', function () {
    $this->settings->enableLdap();

    $ldap_connect = $this->getFunctionMock("App\\Models", "ldap_connect");
    $ldap_connect->expects($this->once())->willReturn('hello');

    $ldap_set_option = $this->getFunctionMock("App\\Models", "ldap_set_option");
    $ldap_set_option->expects($this->exactly(3));

    $blah = Ldap::connectToLdap();
    expect($blah)->toEqual('hello', "LDAP_connect should return 'hello'");
});

test('bind admin', function () {
    $this->settings->enableLdap();
    $this->getFunctionMock("App\\Models", "ldap_bind")->expects($this->once())->willReturn(true);
    expect(Ldap::bindAdminToLdap("dummy"))->toBeNull();
});

test('bind bad', function () {
    $this->settings->enableLdap();
    $this->getFunctionMock("App\\Models", "ldap_bind")->expects($this->once())->willReturn(false);
    $this->getFunctionMock("App\\Models", "ldap_error")->expects($this->once())->willReturn("exception");
    $this->expectExceptionMessage("Could not bind to LDAP:");

    expect(Ldap::bindAdminToLdap("dummy"))->toBeNull();
});

test('anonymous bind', function () {
    //todo - would be nice to introspect somehow to make sure the right parameters were passed?
    $this->settings->enableAnonymousLdap();
    $this->getFunctionMock("App\\Models", "ldap_bind")->expects($this->once())->willReturn(true);
    expect(Ldap::bindAdminToLdap("dummy"))->toBeNull();
});

test('bad anonymous bind', function () {
    $this->settings->enableAnonymousLdap();
    $this->getFunctionMock("App\\Models", "ldap_bind")->expects($this->once())->willReturn(false);
    $this->getFunctionMock("App\\Models", "ldap_error")->expects($this->once())->willReturn("exception");
    $this->expectExceptionMessage("Could not bind to LDAP:");

    expect(Ldap::bindAdminToLdap("dummy"))->toBeNull();
});

test('bad encrypted password', function () {
    $this->settings->enableBadPasswordLdap();

    $this->expectExceptionMessage("Your app key has changed");
    expect(Ldap::bindAdminToLdap("dummy"))->toBeNull();
});

test('find and bind', function () {
    $this->settings->enableLdap();

    $ldap_connect = $this->getFunctionMock("App\\Models", "ldap_connect");
    $ldap_connect->expects($this->once())->willReturn('hello');

    $ldap_set_option = $this->getFunctionMock("App\\Models", "ldap_set_option");
    $ldap_set_option->expects($this->exactly(3));

    $this->getFunctionMock("App\\Models", "ldap_bind")->expects($this->once())->willReturn(true);

    $this->getFunctionMock("App\\Models", "ldap_search")->expects($this->once())->willReturn(true);

    $this->getFunctionMock("App\\Models", "ldap_first_entry")->expects($this->once())->willReturn(true);

    $this->getFunctionMock("App\\Models", "ldap_get_attributes")->expects($this->once())->willReturn(
        [
            "count" => 1,
            0 => [
                'sn' => 'Surname',
                'firstname' => 'FirstName'
            ]
        ]
    );

    $results = Ldap::findAndBindUserLdap("username", "password");
    expect($results)->toEqualCanonicalizing(["count" => 1, 0 => ['sn' => 'Surname', 'firstname' => 'FirstName']]);
});

test('find and bind bad password', function () {
    $this->settings->enableLdap();

    $ldap_connect = $this->getFunctionMock("App\\Models", "ldap_connect");
    $ldap_connect->expects($this->once())->willReturn('hello');

    $ldap_set_option = $this->getFunctionMock("App\\Models", "ldap_set_option");
    $ldap_set_option->expects($this->exactly(3));

    // note - we return FALSE first, to simulate a bad-bind, then TRUE the second time to simulate a successful admin bind
    $this->getFunctionMock("App\\Models", "ldap_bind")->expects($this->exactly(2))->willReturn(false, true);

    //        $this->getFunctionMock("App\\Models","ldap_error")->expects($this->once())->willReturn("exception");
    //        $this->expectExceptionMessage("exception");
    $results = Ldap::findAndBindUserLdap("username", "password");
    expect($results)->toBeFalse();
});

test('find and bind cannot find self', function () {
    $this->settings->enableLdap();

    $ldap_connect = $this->getFunctionMock("App\\Models", "ldap_connect");
    $ldap_connect->expects($this->once())->willReturn('hello');

    $ldap_set_option = $this->getFunctionMock("App\\Models", "ldap_set_option");
    $ldap_set_option->expects($this->exactly(3));

    $this->getFunctionMock("App\\Models", "ldap_bind")->expects($this->once())->willReturn(true);

    $this->getFunctionMock("App\\Models", "ldap_search")->expects($this->once())->willReturn(false);

    $this->expectExceptionMessage("Could not search LDAP:");
    $results = Ldap::findAndBindUserLdap("username", "password");
    expect($results)->toBeFalse();
});

test('find ldap users', function () {
    $this->settings->enableLdap();

    $ldap_connect = $this->getFunctionMock("App\\Models", "ldap_connect");
    $ldap_connect->expects($this->once())->willReturn('hello');

    $ldap_set_option = $this->getFunctionMock("App\\Models", "ldap_set_option");
    $ldap_set_option->expects($this->exactly(3));

    $this->getFunctionMock("App\\Models", "ldap_bind")->expects($this->once())->willReturn(true);

    $this->getFunctionMock("App\\Models", "ldap_search")->expects($this->once())->willReturn(["stuff"]);

    $this->getFunctionMock("App\\Models", "ldap_parse_result")->expects($this->once())->willReturn(true);

    $this->getFunctionMock("App\\Models", "ldap_get_entries")->expects($this->once())->willReturn(["count" => 1]);

    $results = Ldap::findLdapUsers();

    expect($results)->toEqualCanonicalizing(["count" => 1]);
});

test('find ldap users paginated', function () {
    $this->settings->enableLdap();

    $ldap_connect = $this->getFunctionMock("App\\Models", "ldap_connect");
    $ldap_connect->expects($this->once())->willReturn('hello');

    $ldap_set_option = $this->getFunctionMock("App\\Models", "ldap_set_option");
    $ldap_set_option->expects($this->exactly(3));

    $this->getFunctionMock("App\\Models", "ldap_bind")->expects($this->once())->willReturn(true);

    $this->getFunctionMock("App\\Models", "ldap_search")->expects($this->exactly(2))->willReturn(["stuff"]);

    $this->getFunctionMock("App\\Models", "ldap_parse_result")->expects($this->exactly(2))->willReturnCallback(
        function ($ldapconn, $search_results, $errcode, $matcheddn, $errmsg, $referrals, &$controls) {
            static $count = 0;
            if ($count == 0) {
                $count++;
                $controls[LDAP_CONTROL_PAGEDRESULTS]['value']['cookie'] = "cookie";
                return ["count" => 1];
            } else {
                $controls = [];
                return ["count" => 1];
            }

        }
    );

    $this->getFunctionMock("App\\Models", "ldap_get_entries")->expects($this->exactly(2))->willReturn(["count" => 1]);

    $results = Ldap::findLdapUsers();

    expect($results)->toEqualCanonicalizing(["count" => 2]);
});

test('nonexistent tlsfile', function () {
    $this->settings->enableLdap()->set(['ldap_client_tls_cert' => 'SAMPLE CERT TEXT']);
    $certfile = Setting::get_client_side_cert_path();
    $this->assertStringEqualsFile($certfile, 'SAMPLE CERT TEXT');
});

test('stale tlsfile', function () {
    file_put_contents(Setting::get_client_side_cert_path(), 'STALE CERT FILE');
    sleep(1);
    // FIXME - this is going to slow down tests
    $this->settings->enableLdap()->set(['ldap_client_tls_cert' => 'SAMPLE CERT TEXT']);
    $certfile = Setting::get_client_side_cert_path();
    $this->assertStringEqualsFile($certfile, 'SAMPLE CERT TEXT');
});

test('fresh tlsfile', function () {
    $this->settings->enableLdap()->set(['ldap_client_tls_cert' => 'SAMPLE CERT TEXT']);
    $client_side_cert_path = Setting::get_client_side_cert_path();
    file_put_contents($client_side_cert_path, 'WEIRDLY UPDATED CERT FILE');
    clearstatcache();

    //the system should respect our cache-file, since the settings haven't been updated
    $possibly_recached_cert_file = Setting::get_client_side_cert_path();
    //this should *NOT* re-cache from the Settings
    $this->assertStringEqualsFile($possibly_recached_cert_file, 'WEIRDLY UPDATED CERT FILE');
});
