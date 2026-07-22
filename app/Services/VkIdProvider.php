<?php

namespace App\Services;

use GuzzleHttp\RequestOptions;
use Illuminate\Support\Arr;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User;
use RuntimeException;

class VkIdProvider extends AbstractProvider
{
    protected $usesPKCE = true;

    protected $scopes = ['email'];

    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase('https://id.vk.ru/authorize', $state);
    }

    protected function getTokenUrl()
    {
        return 'https://id.vk.ru/oauth2/auth';
    }

    protected function getUserByToken($token)
    {
        $response = $this->getHttpClient()->post('https://id.vk.ru/oauth2/user_info', [
  RequestOptions::HEADERS => ['Accept' => 'application/json'],
  RequestOptions::FORM_PARAMS => [
      'access_token' => $token,
      'client_id' => $this->clientId,
  ],
        ]);

        $contents = (string) $response->getBody();
        $payload = json_decode($contents, true);

        if (! is_array($payload) || ! isset($payload['user']) || ! is_array($payload['user'])) {
  throw new RuntimeException('VK ID returned an invalid user response.');
        }

        return $payload['user'];
    }

    protected function mapUserToObject(array $user)
    {
        return (new User())->setRaw($user)->map([
  'id' => Arr::get($user, 'user_id'),
  'nickname' => null,
  'name' => trim((string) Arr::get($user, 'first_name').' '.(string) Arr::get($user, 'last_name')),
  'email' => Arr::get($user, 'email'),
  'avatar' => Arr::get($user, 'avatar'),
        ]);
    }

    protected function getTokenFields($code)
    {
        return array_merge(parent::getTokenFields($code), [
  'grant_type' => 'authorization_code',
  'device_id' => $this->request->input('device_id'),
        ]);
    }
}
