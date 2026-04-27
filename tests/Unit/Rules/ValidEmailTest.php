<?php

declare(strict_types=1);

use App\Rules\ValidEmail;

it('passes valid email addresses', function (string $email): void {
    expect(validEmailRuleFails($email))->toBeFalse();
})->with([
    'simple' => 'simple@example.com',
    'common dotted local' => 'very.common@example.com',
    'plus symbol' => 'disposable.style.email.with+symbol@example.com',
    'hyphen local' => 'other.email-with-hyphen@example.com',
    'single letter local' => 'x@example.com',
    'hyphen domain' => 'example-indeed@strange-example.com',
    'numeric domain label' => 'admin@mailserver1.com',
    'tag sorting' => 'user.name+tag+sorting@example.com',
    'subdomain' => 'user.name@sub.domain.com',
    'first last hyphen' => 'firstname-lastname@example.com',
    'numeric local' => '1234567890@example.com',
    'local with number' => 'user.123@example.com',
    'domain with number' => 'test456@domain123.com',
    'long local' => 'a.very.long.email.address.but.valid@example.com',
    'country subdomain' => 'another.really.long.email.address@example.co.uk',
    'special allowed' => 'user!#$%&*+/=?^_`{|}~-@example.com',
    'hyphen domain label' => 'user@ex-ample.com',
    'new tld' => 'support@business.dev',
    'long tld' => 'user@domain.museum',
]);

it('fails invalid email addresses', function (string $email): void {
    expect(validEmailRuleFails($email))->toBeTrue();
})->with([
    'uppercase local' => 'R@r.com',
    'uppercase domain' => 'r@R.com',
    'missing local' => '@example.com',
    'missing domain' => 'user@',
    'missing domain label' => 'user@.com',
    'missing tld dot' => 'user@.example',
    'double domain dot' => 'user@sub..example.com',
    'missing at' => 'user',
    'empty' => '',
    'ip address' => 'user@123.123.123.123',
    'bracketed ip' => 'user@[192.168.1.1]',
    'ipv6' => 'user@[IPv6:2001:db8::1]',
    'quoted local' => '"user@with-quotes"@example.com',
    'single quoted local' => "'user@with-quotes'@example.com",
    'quoted spaces' => '"user name"@example.com',
    'unicode local' => 'üñîçødé@example.com',
    'unicode domain' => 'test@éxample.com',
    'short tld' => 'mat@me',
    'localserver' => 'user@localserver',
    'leading junk' => 'junk simple@example.com',
    'trailing junk' => 'simple@example.com junk',
    'wrapped in angle brackets' => '<simple@example.com>',
]);

function validEmailRuleFails(string $email): bool
{
    $failed = false;

    (new ValidEmail)->validate('email', $email, function () use (&$failed): void {
        $failed = true;
    });

    return $failed;
}
