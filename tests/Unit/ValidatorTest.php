<?php

use framework\components\Validator;
use framework\validation\attributes\Email;
use framework\validation\attributes\Required;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $app = createApp();
        $this->validator = $app->validator;

        $this->validator->addValidator('required', new Required());
        $this->validator->addValidator('email', new Email());
    }

    public function testValidateReturnsNoErrorsWhenDataIsValid()
    {
        $data = (object)['name' => 'John'];

        $errors = $this->validator->validate($data, [
            'name' => ['required'],
        ]);

        $this->assertEmpty($errors);
    }

    public function testValidateReturnsErrorsWhenDataIsInvalid()
    {
        $data = (object)['name' => ''];

        $errors = $this->validator->validate($data, [
            'name' => ['required'],
        ]);

        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('name', $errors);
    }

    public function testValidateWithMissingField()
    {
        $data = (object)[];

        $errors = $this->validator->validate($data, [
            'name' => ['required'],
        ]);

        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('name', $errors);
    }

    public function testValidateWithCallableValidator()
    {
        $this->validator->addValidator('uppercase', function ($value) {
            if (strtolower($value) !== $value) {
                return 'Value must be lowercase';
            }
            return '';
        });

        $valid = (object)['name' => 'john'];
        $invalid = (object)['name' => 'John'];

        $this->assertEmpty($this->validator->validate($valid, ['name' => ['uppercase']]));
        $this->assertNotEmpty($this->validator->validate($invalid, ['name' => ['uppercase']]));
    }

    public function testValidateWithPipeStringRules()
    {
        $data = (object)['name' => ''];

        $errors = $this->validator->validate($data, [
            'name' => 'required',
        ]);

        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('name', $errors);
    }

    public function testValidateWithNoRulesReturnsEmptyArray()
    {
        $data = (object)['name' => 'John'];

        $errors = $this->validator->validate($data, []);

        $this->assertEmpty($errors);
    }

    public function testValidateMultipleFields()
    {
        $data = (object)['name' => '', 'email' => ''];

        $errors = $this->validator->validate($data, [
            'name'  => ['required'],
            'email' => ['required'],
        ]);

        $this->assertCount(2, $errors);
        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('email', $errors);
    }
}
