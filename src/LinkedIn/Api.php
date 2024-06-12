<?php

/**
 * This file is part of the Linkedin Share v2 API Integration for Laravel package.
 *
 * Copyright (c) 2016 Alan Brande <alanbrande@lightit.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Homepage:    https://github.com/Light-it-labs/laravel-linkedin-share
 * Version:     1.0
 */

namespace Lightit\LinkedinShare;

use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Config;

class Api
{
    private $redirect_uri;
    private $client_id;
    private $client_secret;

    public function __construct()
    {
        $i = func_num_args();
        if ($i != 3) {
            throw new LinkedinShareException('Invalid arguments. Use REDIRECT_URL, CLIENT_ID and CLIENT_SECRET'.$i);
        }

        $this->redirect_uri = Config::get('larasap.linkedin.redirect_uri');
        $this->client_id = Config::get('larasap.linkedin.client_id');
        $this->client_secret = Config::get('larasap.linkedin.client_secret');
    }

    public function getAccessToken($code)
    {
        $client = new Client();
        $response = $client->request('POST', 'https://www.linkedin.com/oauth/v2/accessToken', [
            'form_params' => [
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'redirect_uri'  => $this->redirect_uri,
                'client_id'     => $this->client_id,
                'client_secret' => $this->client_secret,
            ],
        ]);

        $object = json_decode($response->getBody()->getContents(), true);
        $access_token = $object['access_token'];

        return $access_token;
    }

    public function getProfile($access_token)
    {
        $client = new Client();
        $response = $client->request('GET', 'https://api.linkedin.com/v2/me', [
            'headers' => [
                'Authorization' => 'Bearer '.$access_token,
                'Connection'    => 'Keep-Alive',
            ],
        ]);
        $object = json_decode($response->getBody()->getContents(), true);

        return $object;
    }

    private function registerUpload($access_token, $personURN)
    {
        $client = new Client();

        $response = $client->request('POST', 'https://api.linkedin.com/v2/assets?action=registerUpload', [
            'headers' => [
                'Authorization' => 'Bearer '.$access_token,
                'Connection'    => 'Keep-Alive',
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'registerUploadRequest' => [
                    'recipes' => [
                        'urn:li:digitalmediaRecipe:feedshare-image',
                    ],
                    'owner'                => 'urn:li:person:'.$personURN,
                    'serviceRelationships' => [
                        [
                            'relationshipType' => 'OWNER',
                            'identifier'       => 'urn:li:userGeneratedContent',
                        ],
                    ],
                ],
            ],
        ]);
        $object = json_decode($response->getBody()->getContents(), true);

        return $object;
    }

    private function uploadImage($url, $access_token, $image)
    {
        $client = new Client();
        $client->request('PUT', $url, [
                'headers' => [
                    'Authorization' => 'Bearer '.$access_token,
                ],
                'body' => fopen($image, 'r'),

            ]
        );
    }

    public function shareImage($code, $image, $text, $access_type = 'code')
    {
        $client = new Client();
        $access_token = ($access_type === 'code') ? $this->getAccessToken($code) : $code;
        $personURN = $this->getProfile($access_token)['id'];
        $uploadObject = $this->registerUpload($access_token, $personURN);
        $asset = $uploadObject['value']['asset'];
        $uploadUrl = $uploadObject['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'];
        $this->uploadImage($uploadUrl, $access_token, $image);

        $client->request('POST', 'https://api.linkedin.com/v2/ugcPosts', [
            'headers' => [
                'Authorization'             => 'Bearer '.$access_token,
                'Connection'                => 'Keep-Alive',
                'Content-Type'              => 'application/json',
                'X-Restli-Protocol-Version' => '2.0.0',
            ],
            'json' => [
                'author'          => 'urn:li:person:'.$personURN,
                'lifecycleState'  => 'PUBLISHED',
                'specificContent' => [
                    'com.linkedin.ugc.ShareContent' => [
                        'shareCommentary' => [
                            'text' => $text,
                        ],
                        'shareMediaCategory' => 'IMAGE',
                        'media'              => [
                            [
                                'status' => 'READY',
                                //"originalUrl" => "https://linkedin.com/",
                                'media' => $asset,

                            ],
                        ],
                    ],
                ],
                'visibility' => [
                    'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
                ],
            ],
        ]);
    }

    public function shareArticle($code, $url, $text, $access_type = 'code')
    {
        $client = new Client();
        $access_token = ($access_type === 'code') ? $this->getAccessToken($code) : $code;
        $personURN = $this->getProfile($access_token)['id'];

        $client->request('POST', 'https://api.linkedin.com/v2/ugcPosts', [
            'headers' => [
                'Authorization'             => 'Bearer '.$access_token,
                'Connection'                => 'Keep-Alive',
                'Content-Type'              => 'application/json',
                'X-Restli-Protocol-Version' => '2.0.0',
            ],
            'json' => [
                'author'          => 'urn:li:person:'.$personURN,
                'lifecycleState'  => 'PUBLISHED',
                'specificContent' => [
                    'com.linkedin.ugc.ShareContent' => [
                        'shareCommentary' => [
                            'text' => $text,
                        ],
                        'shareMediaCategory' => 'ARTICLE',
                        'media'              => [
                            [
                                'status'      => 'READY',
                                'originalUrl' => $url,

                            ],
                        ],
                    ],
                ],
                'visibility' => [
                    'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
                ],
            ],
        ]);
    }

    public function shareImageOrg($code, $image, $text, $access_type = 'code')
    {
        $client = new Client();
        $access_token = ($access_type === 'code') ? $this->getAccessToken($code) : $code;
        $personURN = $this->getProfile($access_token)['id'];
        $uploadObject = $this->registerUpload($access_token, $personURN);
        $asset = $uploadObject['value']['asset'];
        $uploadUrl = $uploadObject['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'];
        $this->uploadImage($uploadUrl, $access_token, $image);

        $client->request('POST', 'https://api.linkedin.com/v2/ugcPosts', [
            'headers' => [
                'Authorization'             => 'Bearer '.$access_token,
                'Connection'                => 'Keep-Alive',
                'Content-Type'              => 'application/json',
                'X-Restli-Protocol-Version' => '2.0.0',
            ],
            'json' => [
                'author'          => 'urn:li:person:'.$personURN,
                'lifecycleState'  => 'PUBLISHED',
                'specificContent' => [
                    'com.linkedin.ugc.ShareContent' => [
                        'shareCommentary' => [
                            'text' => $text,
                        ],
                        'shareMediaCategory' => 'IMAGE',
                        'media'              => [
                            [
                                'status' => 'READY',
                                //"originalUrl" => "https://linkedin.com/",
                                'media' => $asset,

                            ],
                        ],
                    ],
                ],
                'visibility' => [
                    'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
                ],
            ],
        ]);
    }

    public function shareArticleOrg($code, $url, $text, $access_type = 'code')
    {
        $client = new Client();
        $access_token = ($access_type === 'code') ? $this->getAccessToken($code) : $code;
        $personURN = $this->getProfile($access_token)['id'];

        $client->request('POST', 'https://api.linkedin.com/v2/ugcPosts', [
            'headers' => [
                'Authorization'             => 'Bearer '.$access_token,
                'Connection'                => 'Keep-Alive',
                'Content-Type'              => 'application/json',
                'X-Restli-Protocol-Version' => '2.0.0',
                'X-RestLi-Method' => 'create',
            ],
            'json' => [
                'author'          => 'urn:li:organization:'.$personURN,
                'lifecycleState'  => 'PUBLISHED',
                'specificContent' => [
                    'com.linkedin.ugc.ShareContent' => [
                        'shareCommentary' => [
                            'text' => $text,
                        ],
                        'shareMediaCategory' => 'ARTICLE',
                        'media'              => [
                            [
                                'status'      => 'READY',
                                'originalUrl' => $url,

                            ],
                        ],
                    ],
                ],
                'visibility' => [
                    'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
                ],
            ],
        ]);
    }

    public function shareNone($code, $text, $access_type = 'code')
    {
        $client = new Client();
        $access_token = ($access_type === 'code') ? $this->getAccessToken($code) : $code;
        $personURN = $this->getProfile($access_token)['id'];

        $client->request('POST', 'https://api.linkedin.com/v2/ugcPosts', [
            'headers' => [
                'Authorization'             => 'Bearer '.$access_token,
                'Connection'                => 'Keep-Alive',
                'Content-Type'              => 'application/json',
                'X-Restli-Protocol-Version' => '2.0.0',
            ],
            'json' => [
                'author'          => 'urn:li:person:'.$personURN,
                'lifecycleState'  => 'PUBLISHED',
                'specificContent' => [
                    'com.linkedin.ugc.ShareContent' => [
                        'shareCommentary' => [
                            'text' => $text,
                        ],
                        'shareMediaCategory' => 'NONE',
                    ],
                ],
                'visibility' => [
                    'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
                ],
            ],
        ]);
    }
}

class LinkedinShareException extends Exception
{
    public function __construct($message, $code = 500, Exception $previous = null)
    {
        // Default code 500
        parent::__construct($message, $code, $previous);
    }
}