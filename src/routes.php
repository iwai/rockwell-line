<?php

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

use LINE\LINEBot\HTTPClient\CurlHTTPClient as LINEBotHTTPClient;
use LINE\LINEBot\Event\MessageEvent;
use LINE\LINEBot\Event\MessageEvent\TextMessage;

use LINE\LINEBot\Exception\InvalidEventRequestException;
use LINE\LINEBot\Exception\InvalidSignatureException;
use LINE\LINEBot\Exception\UnknownEventTypeException;
use LINE\LINEBot\Exception\UnknownMessageTypeException;

/** @var \Slim\App $app */
$app->get('/ping', function ($request, ResponseInterface $response, $args) {

    $response->getBody()->write('pong');

    return $response;
});

$app->post('/webhook', function (ServerRequestInterface $req, ResponseInterface $res, $args) {

    $channelSecret      = getenv('LINE_CHANNEL_SECRET');
    $channelAccessToken = getenv('LINE_CHANNEL_ACCESS_TOKEN');

    try {
        $bot = new \LINE\LINEBot(
            new LINEBotHTTPClient($channelAccessToken),
            [ 'channelSecret' => $channelSecret ]
        );
        $this->logger->info(sprintf(
            'Sign: %s Request body: %s', $req->getHeader('X_LINE_SIGNATURE'), $req->getBody()
        ));

        $events = $bot->parseEventRequest(
            $req->getBody(), $req->getHeader('X_LINE_SIGNATURE')
        );

    } catch (InvalidSignatureException $e) {
        $this->logger->info(sprintf('%s %s', 400, 'Invalid signature'));
        return $res->withStatus(400, 'Invalid signature');
    } catch (UnknownEventTypeException $e) {
        $this->logger->info(sprintf('%s %s', 400, 'Unknown event type has come'));
        return $res->withStatus(400, 'Unknown event type has come');
    } catch (UnknownMessageTypeException $e) {
        $this->logger->info(sprintf('%s %s', 400, 'Unknown message type has come'));
        return $res->withStatus(400, 'Unknown message type has come');
    } catch (InvalidEventRequestException $e) {
        $this->logger->info(sprintf('%s %s', 400, 'Invalid event request'));
        return $res->withStatus(400, 'Invalid event request');
    } catch (\Exception $e) {
        $this->logger->info(sprintf('%s %s', 500, 'Internal Server Error'));
        return $res->withStatus(500, 'Internal Server Error');
    }

    foreach ($events as $event) {
        if (!($event instanceof MessageEvent)) {
            $this->logger->info('Non message event has come');
            continue;
        }
        if (!($event instanceof TextMessage)) {
            $this->logger->info('Non text message has come');
            continue;
        }

        $replyText = $event->getText();

        $response = $bot->replyText($event->getReplyToken(), $replyText);

        $this->logger->info(sprintf(
            '%s %s', $response->getHTTPStatus(), $response->getRawBody()
        ));
    }

    $res->getBody()->write('OK');

    return $res;
});
