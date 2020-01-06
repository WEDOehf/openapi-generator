## Open API Generator
Framework agnostic Open api generator from phpdoc and php type hints

-----
[![Latest Stable Version](https://poser.pugx.org/wedo/openapi-generator/v/stable)](https://packagist.org/packages/wedo/openapi-generator)
[![Build Status](https://travis-ci.org/WEDOEhf/openapi-generator.svg?branch=master)](https://travis-ci.org/WEDOEhf/openapi-generator)
[![codecov](https://codecov.io/gh/WEDOEhf/openapi-generator/branch/master/graph/badge.svg)](https://codecov.io/gh/WEDOEhf/openapi-generator)
[![PHPStan](https://img.shields.io/badge/PHPStan-enabled-brightgreen.svg?style=flat)](https://github.com/phpstan/phpstan)
[![License](https://poser.pugx.org/wedo/openapi-generator/license)](https://packagist.org/packages/wedo/openapi-generator)

## Installation

	$ composer require wedo/openapi-generator

## Configuration

see src/Config.php

## Usage
```php
//minimal config
$config = new Config();
$config->baseRequest = BaseRequest::class;
$config->serverUrl = 'https://www.mydomain.com/api/v1';
$config->baseEnum = BaseEnum::class; // use with @see annotation if U want to provide user enum options
$config->requiredAnnotation = '@required'; //what annotation should be used on requests to confirm that parameter is required
$config->namespace = 'App\Api\Controllers\\';
$config->path = __DIR__ . 'Api/Controllers';

$generator = new Generator($config);

//add standard error response to ref list
$this->generator->onBeforeGenerate[] = function () {
    $this->generator->getRefProcessor()->generateRef(ClassType::from(ErrorResponse::class));
};

$this->generator->getClassProcessor()->getMethodProcessor()->onProcess[] = function() {
        // set some standard error responses for each endpoint
        $methodProcessor = $this->generator->getClassProcessor()->getMethodProcessor();
        $path->responses[400] = $methodProcessor->createResponse('Bad request error response', 'ErrorResponse');
        
        $annotations = $method->getAnnotations();
        if (!empty($annotations['throws'])) {
            // add your own error responses classes for some specific exceptions
        }
}

//set some title
$this->generator->getJson()->info->title = 'My api';

//add your security schemes if needed

$this->generator->getJson()->components->securitySchemes = [
    'APIKeyHeader' => [
            'type' => 'apiKey',
            'in' => 'header',
            'name' => 'api-key',
        ]
];

$json = $this->generator->generate();
file_put_contents('open-api.json', $json);
```
