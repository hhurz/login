<?php

declare(strict_types=1);

namespace atk4\login\Field;

use atk4\core\InitializerTrait;
use atk4\data\Exception;
use atk4\data\Field;
use atk4\data\Persistence;
use atk4\ui\Persistence\UI;

class Password extends Field
{
    use InitializerTrait {
        init as _init;
    }

    /** @var string data field type */
    public $type = 'password';

    /**
     * Keeping the actual hash protected, in case we have to validate password with
     * compare().
     *
     * @var string
     */
    protected $password_hash;

    /**
     * Optional callable for encrypting password.
     * Use it if you need to customize your password encryption algorithm.
     * Receives parameters - plaintext password.
     *
     * @var callable
     */
    public $encryptMethod;

    /**
     * Optional callable for verifying password.
     * Use it if you need to customize your password verification algorithm.
     * Receives parameters - plaintext password, encrypted password.
     *
     * @var callable
     */
    public $verifyMethod;

    /**
     * Initialization.
     */
    protected function init(): void
    {
        $this->_init();
        $this->setDefaultTypecastMethods();
    }

    /**
     * Cloning function.
     */
    public function __clone()
    {
        // IMPORTANT: This is required as workaround in case you clone model.
        // Otherwise it will use encrypt/decrypt method of old model object.
        $this->setDefaultTypecastMethods();
    }

    /**
     * Sets default typecast methods.
     */
    protected function setDefaultTypecastMethods()
    {
        $this->typecast = [
            // callback on saving
            function (string $password, Field $f, Persistence $p) {
                return $this->encrypt($password, $f, $p);
            },
            // callback on loading
            function (string $password, Field $f, Persistence $p) {
                return $this->decrypt($password, $f, $p);
            },
        ];
    }

    /**
     * Normalize password - remove hash.
     *
     * @param string $value password
     *
     * @return mixed
     */
    public function normalize($value)
    {
        $this->password_hash = null;

        return parent::normalize($value);
    }

    /**
     * DO NOT CALL THIS METHOD. It is automatically invoked when you save
     * your model.
     *
     * When storing password to persistence, it will be encrypted. We will
     * also update $this->password_hash, in case you'll want to perform
     * verify right after.
     *
     * @param string $password plaintext password
     *
     * @return string|null encrypted password
     */
    public function encrypt(string $password, Field $f, Persistence $p)
    {
        if ($password === null) {
            return null;
        }

        // encrypt password
        if (is_callable($this->encryptMethod)) {
            $this->password_hash = call_user_func_array($this->encryptMethod, [$password]);
        } else {
            $this->password_hash = password_hash($password, PASSWORD_DEFAULT);
        }

        return $this->password_hash;
    }

    /**
     * DO NOT CALL THIS METHOD. It is automatically invoked when you load
     * your model.
     *
     * @param string $password encrypted password
     *
     * @return string|null encrypted password
     */
    public function decrypt(string $password, Field $f, Persistence $p)
    {
        $this->password_hash = $password;
        if ($p instanceof UI) {
            return $password;
        }

        return null;
    }

    /**
     * Verify if the password user have supplied you with is correct.
     *
     * @param string $password plain text password
     *
     * @return bool true if passwords match
     */
    public function compare($password): bool
    {
        if ($this->password_hash === null) {
            // perhaps we currently hold a password and it's not saved yet.
            $v = $this->get();

            if ($v) {
                return $v === $password;
            }

            throw (new Exception('Password was not set, so verification is not possible'))
                ->addMoreInfo('field', $this->name);
        }

        // verify password
        if (is_callable($this->verifyMethod)) {
            $v = call_user_func_array($this->verifyMethod, [$password, $this->password_hash]);
        } else {
            $v = password_verify($password, $this->password_hash);
        }

        return $v;
    }

    /**
     * Randomly generate a password, that is easy to memorize. There are
     * 116985856 unique password combinations with length of 4.
     *
     * To make this more complex, use suggestPasssword(3).' '.suggestPassword(3);
     */
    public function suggestPassword(int $length = 4, int $words = 1): string
    {
        $p5 = ['', 'k', 's', 't', 'n', 'h', 'm', 'r', 'w', 'g', 'z', 'd', 'b', 'p'];
        $p3 = ['y', 'ky', 'sh', 'ch', 'ny', 'my', 'ry', 'gy', 'j', 'py', 'by'];
        $a5 = ['a', 'i', 'u', 'e', 'o'];
        $a3 = ['a', 'u', 'o'];
        $syl = ['n'];

        foreach ($p5 as $p) {
            foreach ($a5 as $a) {
                $syl[] = $p . $a;
            }
        }

        foreach ($p3 as $p) {
            foreach ($a3 as $a) {
                $syl[] = $p . $a;
            }
        }

        $pass = '';

        for ($i = 0; $i < $length; ++$i) {
            $pass .= $syl[array_rand($syl)];
        }

        return $pass;
    }
}
