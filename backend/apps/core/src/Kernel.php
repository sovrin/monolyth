<?php

namespace Thomann\Core;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use function dirname;

final class Kernel extends BaseKernel {
    use MicroKernelTrait;

    // Make %kernel.project_dir% resolve to backend/
    public function getProjectDir (): string {
        // apps/core/src -> apps/core -> apps -> backend (3 ups)
        return dirname(__DIR__, 3);
    }

    protected function configureContainer (ContainerConfigurator $c): void {
        $d = $this->getProjectDir() . '/config';
        $c->import($d . '/{packages}/*.yaml');
        $c->import($d . '/{packages}/' . $this->environment . '/*.yaml');
        $c->import($d . '/services.yaml');
        $c->import($d . '/{services}_' . $this->environment . '.yaml');
    }

    protected function configureRoutes (RoutingConfigurator $r): void {
        $d = $this->getProjectDir() . '/config';
        $r->import($d . '/{routes}/' . $this->environment . '/*.yaml');
        $r->import($d . '/{routes}/*.yaml');
    }
}
