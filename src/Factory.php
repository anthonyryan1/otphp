<?php

declare(strict_types=1);

namespace OTPHP;

use Assert\Assertion;
use function count;
use InvalidArgumentException;
use Throwable;

/**
 * This class is used to load OTP object from a provisioning Uri.
 */
final class Factory implements FactoryInterface
{
    public static function loadFromProvisioningUri(string $uri): OTPInterface
    {
        try {
            $parsed_url = Url::fromString($uri);
            Assertion::eq('otpauth', $parsed_url->getScheme());
        } catch (Throwable $throwable) {
            throw new InvalidArgumentException('Not a valid OTP provisioning URI', $throwable->getCode(), $throwable);
        }

        $otp = self::createOTP($parsed_url);

        self::populateOTP($otp, $parsed_url);

        return $otp;
    }

    private static function populateParameters(OTPInterface $otp, Url $data): void
    {
        foreach ($data->getQuery() as $key => $value) {
            $otp->setParameter($key, $value);
        }
    }

    private static function populateOTP(OTPInterface $otp, Url $data): void
    {
        self::populateParameters($otp, $data);
        $result = explode(':', rawurldecode(mb_substr($data->getPath(), 1)));

        if (count($result) < 2) {
            $otp->setIssuerIncludedAsParameter(false);

            return;
        }

        if ($otp->getIssuer() !== null) {
            Assertion::eq($result[0], $otp->getIssuer(), 'Invalid OTP: invalid issuer in parameter');
            $otp->setIssuerIncludedAsParameter(true);
        }
        $otp->setIssuer($result[0]);
    }

    private static function createOTP(Url $parsed_url): OTPInterface
    {
        switch ($parsed_url->getHost()) {
            case 'totp':
                $totp = TOTP::create($parsed_url->getSecret());
                $totp->setLabel(self::getLabel($parsed_url->getPath()));

                return $totp;
            case 'hotp':
                $hotp = HOTP::create($parsed_url->getSecret());
                $hotp->setLabel(self::getLabel($parsed_url->getPath()));

                return $hotp;
            default:
                throw new InvalidArgumentException(sprintf('Unsupported "%s" OTP type', $parsed_url->getHost()));
        }
    }

    private static function getLabel(string $data): string
    {
        $result = explode(':', rawurldecode(mb_substr($data, 1)));

        return count($result) === 2 ? $result[1] : $result[0];
    }
}
