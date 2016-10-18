<?php

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

use LINE\LINEBot\HTTPClient\CurlHTTPClient as LINEBotHTTPClient;
use LINE\LINEBot\Event\FollowEvent;
use LINE\LINEBot\Event\UnfollowEvent;
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
        $signature = $req->getHeader('X_LINE_SIGNATURE');

        $this->logger->info(sprintf(
            'Sign: %s Request body: %s', $signature[0], $req->getBody()
        ));

        $events = $bot->parseEventRequest(
            $req->getBody(), $signature[0]
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
        if ($event instanceof FollowEvent) {

            $r = $bot->getProfile($event->getUserId());

            if ($r->isSucceeded()) {
                $profile = $r->getJSONDecodedBody();

                $response = $bot->replyText(
                    $event->getReplyToken(),
                    sprintf("%s さん、友達追加ありがとう！", $profile['displayName'])
                );
            }

        } elseif ($event instanceof UnfollowEvent) {

        } elseif ($event instanceof TextMessage) {

            if ($event->isGroupEvent() || $event->isRoomEvent()) {
                if (preg_match('/^でていけ|出て行け|消えろ$/u', $event->getText()) === 1) {
                    $event->isGroupEvent()
                        ? $bot->leaveGroup($event->getGroupId())
                        : $bot->leaveRoom($event->getRoomId());
                    continue;
                }
            }

            if ($event->isUserEvent() && preg_match('/^プロフィール$/u', $event->getText()) === 1) {
                $r = $bot->getProfile($event->getUserId());

                if ($r->isSucceeded()) {
                    $profile = $r->getJSONDecodedBody();

                    $response = $bot->replyText(
                        $event->getReplyToken(),
                        sprintf("%s さん\nUrl: %s\nStatus: %s", $profile['displayName'], $profile['pictureUrl'], $profile['statusMessage'])
                    );

                    $this->logger->info(sprintf(
                        '%s %s', $response->getHTTPStatus(), $response->getRawBody()
                    ));
                }
            } else {

                $client = new GuzzleHttp\Client();
                $response = $client->post('https://api.yelp.com/oauth2/token',  [
                    'form_params' => [
                        'grant_type' => 'client_credentials',
                        'client_id' => getenv('YELP_APP_ID'),
                        'client_secret' => getenv('YELP_APP_SECRET')
                    ]
                ]);
                $this->logger->info($response->getBody());

                $replyText = $event->getText();

                $response = $bot->replyText($event->getReplyToken(), $replyText);

                $this->logger->info(sprintf(
                    '%s %s', $response->getHTTPStatus(), $response->getRawBody()
                ));

            }
        } elseif ($event instanceof MessageEvent\ImageMessage) {
            $r = $bot->getMessageContent($event->getMessageId());
        }
    }

    $res->getBody()->write('OK');

    return $res;
});
