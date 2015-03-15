<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\MvcAuth\Factory;

use Zend\Authentication\Adapter\Http as HttpAuth;
use Zend\ServiceManager\Exception\ServiceNotCreatedException;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use ZF\MvcAuth\Authentication\DefaultAuthenticationListener;
use ZF\MvcAuth\Authentication\HttpAdapter;
use ZF\MvcAuth\Authentication\OAuth2Adapter;
use OAuth2\Server as OAuth2Server;
use OAuth2\GrantType\ClientCredentials;
use OAuth2\GrantType\AuthorizationCode;

/**
 * Factory for creating the DefaultAuthenticationListener from configuration
 */
class DefaultAuthenticationListenerFactory implements FactoryInterface
{
    /**
     * @param ServiceLocatorInterface $services
     * @return DefaultAuthenticationListener
     */
    public function createService(ServiceLocatorInterface $services)
    {
        $listener = new DefaultAuthenticationListener();

        $httpAdapter = $this->retrieveHttpAdapter($services);
        if ($httpAdapter) {
            $listener->attach($httpAdapter);
        }

        $oauth2Server = $this->createOAuth2Server($services);
        if ($oauth2Server) {
            $listener->attach($oauth2Server);
        }

        $authenticationTypes = $this->getAuthenticationTypes($services);
        if ($authenticationTypes) {
            $listener->addAuthenticationTypes($authenticationTypes);
        }

        return $listener;
    }

    /**
     * @param  ServiceLocatorInterface $services
     * @throws ServiceNotCreatedException
     * @return false|HttpAdapter
     */
    protected function retrieveHttpAdapter(ServiceLocatorInterface $services)
    {
        // Allow applications to provide their own AuthHttpAdapter service; if none provided,
        // or no HTTP adapter configuration provided to zf-mvc-auth, we can stop early.
        $httpAdapter = $services->get('ZF\MvcAuth\Authentication\AuthHttpAdapter');
        if ($httpAdapter === false) {
            return false;
        }

        // We must abort if no resolver was provided
        if (!$httpAdapter->getBasicResolver()
            && !$httpAdapter->getDigestResolver()
        ) {
            return false;
        }

        $authService = $services->get('authentication');
        return new HttpAdapter($httpAdapter, $authService);
    }

    /**
     * Create an OAuth2 server by introspecting the config service
     *
     * @param  ServiceLocatorInterface $services
     * @throws \Zend\ServiceManager\Exception\ServiceNotCreatedException
     * @return false|OAuth2Adapter
     */
    protected function createOAuth2Server(ServiceLocatorInterface $services)
    {
        if (!$services->has('config')) {
            return false;
        }

        $config = $services->get('config');
        if (!isset($config['zf-oauth2']['storage'])
            || !is_string($config['zf-oauth2']['storage'])
            || !$services->has($config['zf-oauth2']['storage'])
        ) {
            return false;
        }

        $storage = $services->get($config['zf-oauth2']['storage']);

        // Pass a storage object or array of storage objects to the OAuth2 server class
        $oauth2Server = new OAuth2Server($storage);

        // Add the "Client Credentials" grant type (it is the simplest of the grant types)
        $oauth2Server->addGrantType(new ClientCredentials($storage));

        // Add the "Authorization Code" grant type
        $oauth2Server->addGrantType(new AuthorizationCode($storage));

        return new OAuth2Adapter($oauth2Server);
    }

    /**
     * Retrieve custom authentication types
     * 
     * @param ServiceLocatorInterface $services 
     * @return false|array
     */
    protected function getAuthenticationTypes(ServiceLocatorInterface $services)
    {
        if (! $services->has('config')) {
            return false;
        }

        $config = $services->get('config');
        if (! isset($config['zf-mvc-auth']['authentication']['types'])
            || ! is_array($config['zf-mvc-auth']['authentication']['types'])
        ) {
            return false;
        }

        return $config['zf-mvc-auth']['authentication']['types'];
    }
}
