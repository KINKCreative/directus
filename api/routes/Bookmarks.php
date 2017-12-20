<?php

namespace Directus\Api\Routes;

use Directus\Application\Application;
use Directus\Application\Http\Request;
use Directus\Application\Http\Response;
use Directus\Application\Route;
use Directus\Database\TableGateway\DirectusBookmarksTableGateway;
use Directus\Database\TableGateway\DirectusPreferencesTableGateway;
use Directus\Database\TableGateway\RelationalTableGateway;
use Directus\Permissions\Acl;
use Directus\Util\StringUtils;

class Bookmarks extends Route
{
    /**
     * @param Application $app
     */
    public function __invoke(Application $app)
    {
        $app->get('', [$this, 'all']);
        $app->post('', [$this, 'create']);
        $app->map(['POST', 'PUT', 'PATCH', 'DELETE'], '/bookmarks/{id}', [$this, 'one']);
        $app->get('/user/me', [$this, 'mine']);
        $app->get('/user/{id}', [$this, 'user']);
        $app->get('/{title}/preferences', [$this, 'preferences']);
    }

    /**
     * @param Request $request
     * @param Response $response
     *
     * @return Response
     */
    public function all(Request $request, Response $response)
    {
        $acl = $this->container->get('acl');
        $dbConnection = $this->container->get('database');
        $tableGateway = new RelationalTableGateway('directus_bookmarks', $dbConnection, $acl);

        $params = $request->getQueryParams();

        $data = $this->getEntriesAndSetResponseCacheTags($tableGateway, $params);

        return $this->withData($response, $data);
    }

    /**
     * @param Request $request
     * @param Response $response
     *
     * @return Response
     */
    public function create(Request $request, Response $response)
    {
        return $this->one($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     *
     * @return Response
     */
    public function one(Request $request, Response $response)
    {
        $acl = $this->container->get('acl');
        $dbConnection = $this->container->get('database');
        $payload = $request->getParsedBody();
        $id = $request->getAttribute('id');

        $currentUserId = $acl->getUserId();
        $bookmarks = new DirectusBookmarksTableGateway($dbConnection, $acl);
        $preferences = new DirectusPreferencesTableGateway($dbConnection, null);

        $this->validateRequestWithTable($request, 'directus_bookmarks');

        switch ($request->getMethod()) {
            case 'PATCH':
            case 'PUT':
                $bookmarks->updateBookmark($payload);
                $id = $payload['id'];
                break;
            case 'POST':
                $payload['user'] = $currentUserId;
                $id = $bookmarks->insertBookmark($payload);
                break;
            case 'DELETE':
                $bookmark = $bookmarks->fetchEntityByUserAndId($currentUserId, $id);
                $data = [];

                if (!empty($bookmark['data'])) {
                    $success = (bool) $bookmarks->delete(['id' => $id]);

                    // delete the preferences
                    $preferences->delete([
                        'user' => $currentUserId,
                        'title' => $bookmark['data']['title']
                    ]);
                } else {
                    $data['error'] = [
                        'message' => __t('bookmark_not_found')
                    ];
                }

                return $this->withData($response, $data);
        }

        if (!is_null($id)) {
            $jsonResponse = $this->getDataAndSetResponseCacheTags([$bookmarks, 'fetchEntityByUserAndId'], [$currentUserId, $id]);
        } else {
            $jsonResponse = $this->getDataAndSetResponseCacheTags([$bookmarks, 'fetchEntitiesByUserId'], [$currentUserId]);
        }

        return $this->withData($response, $jsonResponse);
    }

    /**
     * @param Request $request
     * @param Response $response
     *
     * @return Response
     */
    public function user(Request $request, Response $response)
    {
        $id = $request->getAttribute('id');

        return $this->processUserBookmarks($id, $request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     *
     * @return Response
     */
    public function mine(Request $request, Response $response)
    {
        /** @var Acl $acl */
        $acl = $this->container->get('acl');
        $id = $acl->getUserId();

        return $this->processUserBookmarks($id, $request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     *
     * @return Response
     */
    public function preferences(Request $request, Response $response)
    {
        /** @var Acl $acl */
        $acl = $this->container->get('acl');
        $dbConnection = $this->container->get('database');
        $tableGateway = new DirectusPreferencesTableGateway($dbConnection, $acl);
        $title = $request->getAttribute('title');
        $params = $request->getQueryParams();

        $data = $this->getDataAndSetResponseCacheTags(
            [$tableGateway, 'fetchEntityByUserAndTitle'],
            [$acl->getUserId(), $title, $params]
        );

        if (!isset($data['data'])) {
            $data = [
                'error' => [
                    'message' => __t('bookmark_not_found')
                ]
            ];
        }

        return $this->withData($response, $data);
    }

    /**
     * @param int $userId
     *
     * @return Response
     */
    public function processUserBookmarks($userId, Request $request, Response $response)
    {
        $acl = $this->container->get('acl');
        $dbConnection = $this->container->get('database');
        $payload = $request->getParsedBody();
        $params = $request->getQueryParams();

        $bookmarks = new DirectusBookmarksTableGateway($dbConnection, $acl);
        switch ($request->getMethod()) {
            case 'PUT':
                $bookmarks->updateBookmark($payload);
                $id = $payload['id'];
                break;
            case 'POST':
                $requestPayload['user'] = $userId;
                $id = $bookmarks->insertBookmark($payload);
                break;
        }

        $data = $this->getDataAndSetResponseCacheTags([$bookmarks, 'fetchEntitiesByUserId'], [$userId, $params]);

        return $this->withData($response, $data);
    }
}
