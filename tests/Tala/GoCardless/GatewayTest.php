<?php

/*
 * This file is part of the Tala Payments package.
 *
 * (c) Adrian Macneil <adrian@adrianmacneil.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tala\GoCardless;

use Mockery as m;
use Tala\CreditCard;
use Tala\Request;

class GatewayTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->gateway = new Gateway(array('username' => 'abc', 'password' => '123'));

        $this->request = new Request;
        $this->request->amount = 1000;
        $this->request->returnUrl = 'https://www.example.com/complete';

        $this->card = new CreditCard;

        $this->browser = m::mock('\Buzz\Browser');
        $this->gateway->setBrowser($this->browser);

        $this->httpRequest = m::mock('\Symfony\Component\HttpFoundation\Request');
        $this->gateway->setHttpRequest($this->httpRequest);
    }

    public function testPurchase()
    {
        $response = $this->gateway->purchase($this->request, $this->card);

        $this->assertInstanceOf('\Tala\RedirectResponse', $response);
        $this->assertStringStartsWith('https://gocardless.com/connect/bills/new?', $response->getRedirectUrl());
    }

    public function testCompletePurchaseSuccess()
    {
        $this->httpRequest->shouldReceive('get')->with('resource_uri')->once()->andReturn('a');
        $this->httpRequest->shouldReceive('get')->with('resource_id')->once()->andReturn('b');
        $this->httpRequest->shouldReceive('get')->with('resource_type')->once()->andReturn('c');
        $this->httpRequest->shouldReceive('get')->with('signature')->once()
            ->andReturn('416f52e7d287dab49fa8445c1cd0957ca8ddf1c04a6300e00117dc0bedabc7d7');

        $browserResponse = m::mock('Buzz\Message\Response');
        $browserResponse->shouldReceive('getContent')->once()
            ->andReturn('{"success":true}');

        $this->browser->shouldReceive('post')
            ->with('https://gocardless.com/api/v1/confirm', m::type('array'), m::type('string'))->once()
            ->andReturn($browserResponse);

        $response = $this->gateway->completePurchase($this->request);

        $this->assertInstanceOf('Tala\GoCardless\Response', $response);
        $this->assertEquals('b', $response->getGatewayReference());
    }

    /**
     * @expectedException Tala\Exception
     * @expectedExceptionMessage The resource cannot be confirmed
     */
    public function testCompletePurchaseError()
    {
        $this->httpRequest->shouldReceive('get')->with('resource_uri')->once()->andReturn('a');
        $this->httpRequest->shouldReceive('get')->with('resource_id')->once()->andReturn('b');
        $this->httpRequest->shouldReceive('get')->with('resource_type')->once()->andReturn('c');
        $this->httpRequest->shouldReceive('get')->with('signature')->once()
            ->andReturn('416f52e7d287dab49fa8445c1cd0957ca8ddf1c04a6300e00117dc0bedabc7d7');

        $browserResponse = m::mock('Buzz\Message\Response');
        $browserResponse->shouldReceive('getContent')->once()
            ->andReturn('{"error":["The resource cannot be confirmed"]}');

        $this->browser->shouldReceive('post')
            ->with('https://gocardless.com/api/v1/confirm', m::type('array'), m::type('string'))->once()
            ->andReturn($browserResponse);

        $response = $this->gateway->completePurchase($this->request);
    }

    /**
     * @expectedException Tala\Exception\InvalidResponseException
     */
    public function testCompletePurchaseInvalid()
    {
        $this->httpRequest->shouldReceive('get')->with('resource_uri')->once()->andReturn('a');
        $this->httpRequest->shouldReceive('get')->with('resource_id')->once()->andReturn('b');
        $this->httpRequest->shouldReceive('get')->with('resource_type')->once()->andReturn('c');
        $this->httpRequest->shouldReceive('get')->with('signature')->once()->andReturn('d');

        $this->gateway->completePurchase($this->request);
    }
}
