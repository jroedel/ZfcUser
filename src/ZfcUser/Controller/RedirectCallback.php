<?php

namespace ZfcUser\Controller;

use Zend\Mvc\Application;
use Zend\Router\RouteInterface;
use Zend\Router\Exception;
use Zend\Http\PhpEnvironment\Response;
use ZfcUser\Options\ModuleOptions;
use Zend\Http\Request;
use Zend\Router\RouteMatch;

/**
 * Buils a redirect response based on the current routing and parameters
 */
class RedirectCallback
{

    /** @var RouteInterface  */
    private $router;

    /** @var Application */
    private $application;

    /** @var ModuleOptions */
    private $options;

    /**
     * @param Application $application
     * @param RouteInterface $router
     * @param ModuleOptions $options
     */
    public function __construct(Application $application, RouteInterface $router, ModuleOptions $options)
    {
        $this->router = $router;
        $this->application = $application;
        $this->options = $options;
    }

    /**
     * @return Response
     */
    public function __invoke()
    {
        $routeMatch = $this->application->getMvcEvent()->getRouteMatch();
        $redirect = $this->getRedirect($routeMatch->getMatchedRouteName(), $this->getRedirectRouteFromRequest());

        $response = $this->application->getResponse();
        $response->getHeaders()->addHeaderLine('Location', $redirect);
        $response->setStatusCode(302);
        return $response;
    }

    /**
     * Return the redirect from param.
     * First checks GET then POST
     * @return string
     */
    private function getRedirectRouteFromRequest()
    {
        $request  = $this->application->getRequest();
        $redirect = $request->getQuery('redirect');
        if ($redirect && $this->routeExists($redirect)) {
            return $redirect;
        }

        $redirect = $request->getPost('redirect');
        if ($redirect && $this->routeExists($redirect)) {
            return $redirect;
        }

        return false;
    }

    /**
     * @param $route
     * @return bool
     */
    private function routeExists($route)
    {
        try {
            $testRequest = new Request();
            $testRequest->setUri($route);
            $match = $this->router->match($testRequest);
            if (!$match instanceof RouteMatch) {
                return false;
            }
        } catch (Exception\RuntimeException $e) {
            return false;
        }
        return true;
    }

    /**
     * Returns the url to redirect to based on current route.
     * If $redirect is set and the option to use redirect is set to true, it will return the $redirect url.
     *
     * @param string $currentRoute
     * @param bool $redirect
     * @return mixed
     */
    protected function getRedirect($currentRoute, $redirect = false)
    {
        $useRedirect = $this->options->getUseRedirectParameterIfPresent();
        $routeExists = ($redirect && $this->routeExists($redirect));
        if (!$useRedirect || !$routeExists) {
            $redirect = false;
        }

        switch ($currentRoute) {
            case 'zfcuser/register':
            case 'zfcuser/login':
            case 'zfcuser/authenticate':
                if ($redirect) {
                    return $this->assembleRedirect($redirect);
                }
                $route = $this->options->getLoginRedirectRoute();
                return $this->router->assemble(array(), array('name' => $route));
                break;
            case 'zfcuser/logout':
                if ($redirect) {
                    return $this->assembleRedirect($redirect);
                }
                $route = $this->options->getLogoutRedirectRoute();
                return $this->router->assemble(array(), array('name' => $route));
                break;
            default:
                return $this->router->assemble(array(), array('name' => 'zfcuser'));
        }
    }
    
    /**
     * Assemble a route from redirect text
     *
     * @param string $redirect
     * @throws \Exception
     * @return mixed
     */
    protected function assembleRedirect($redirect)
    {
        $request = new Request();
        $request->setUri($redirect);
        $match = $this->router->match($request);
        if (!$match instanceof RouteMatch) {
            throw new \Exception('Invalid redirect "'.$redirect.'"');
        }
        return $this->router->assemble($match->getParams(), array('name' => $match->getMatchedRouteName()));
    }
}
