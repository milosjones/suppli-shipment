<?php

namespace App\OpenApi;

use Dedoc\Scramble\Contracts\OperationTransformer;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\RouteInfo;

class CompanyHeaderTransformer implements OperationTransformer
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        if (! in_array('tenant', $routeInfo->route->gatherMiddleware(), true)) {
            return;
        }

        $operation->addParameters([
            (new Parameter('X-Company-Id', 'header'))
                ->setSchema(Schema::fromType(new IntegerType))
                ->description('Company to work in. Required for super admins; company users and integrations always work in their own company.'),
        ]);
    }
}
