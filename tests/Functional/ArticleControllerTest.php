<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\ArticleRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;

class ArticleControllerTest extends WebTestCase
{
    // Propriété qui va stocker notre client léger (pour envoyer des requêtes)
    private KernelBrowser $client;
    private AbstractDatabaseTool $databaseTool;

    public function setUp(): void
    {
        // Création du client léger pour les tests
        $this->client = self::createClient(server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $this->databaseTool = self::getContainer()->get(DatabaseToolCollection::class)->get();
    }

    // Fonction pour récupérer un utilisateur
    private function getUser(string $username = 'admin'): ?User
    {
        // On load les fixtures
        $this->databaseTool->loadAliceFixture([
            __DIR__ . '/Fixtures/UserFixtures.yaml'
        ]);

        // On récupère l'utilisateur par son nom d'utilisateur
        $user = self::getContainer()->get(UserRepository::class)
            ->findOneBy(['username' => $username]);

        // On le renvois
        return $user;
    }

    // Test pour vérifier que l'endpoint retourne un code 401 quand aucun utilisateur n'est connecté
    public function testIndexEndpointWithNoConnectedUser(): void
    {
        $this->client->request('GET', '/api/admin/articles');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    // Test pour vérifier que l'endpoint retourne un code 403 quand un user est connecté
    public function testIndexEndpointWithConnectedUser(): void
    {
        $this->client->loginUser($this->getUser('user'), 'login');

        $this->client->request('GET', '/api/admin/articles');
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    // Test pour vérifier que l'endpoint retourne un code 200 quand un admin est connecté
    public function testIndexEndpointWithConnectedAdminUser(): void
    {
        $this->client->loginUser($this->getUser('admin'), 'login');

        $this->client->request('GET', '/api/admin/articles');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    // Test pour vérifier que l'endpoint retourne la structure JSON correcte
    public function testIndexEndpointValidateStructureJsonResponse(): void
    {
        $this->client->loginUser($this->getUser('admin'), 'login');

        $this->client->request('GET', '/api/admin/articles');

        $response = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertIsArray($response);
        $this->assertArrayHasKey('items', $response);
        $this->assertArrayHasKey('meta', $response);
        $this->assertArrayHasKey('pages', $response['meta']);
        $this->assertArrayHasKey('total', $response['meta']);
    }

    // Test pour vérifier que l'endpoint retourne le bon nombre d'articles par défaut
    public function testIndexEndPointValidateNumberOfItemsDefault(): void
    {
        $this->client->loginUser($this->getUser('admin'), 'login');

        // On charge les fixtures pour le test avec notre méthode en tête
        $this->databaseTool->loadAliceFixture([
            __DIR__ . '/Fixtures/ArticleFixture.yaml'
        ]);

        $this->client->request('GET', '/api/admin/articles');

        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertCount(6, $response['items']);
    }

    // Test pour vérifier que l'endpoint retourne le bon nombre d'articles avec le paramètre limit
    public function testIndexEndPointValidateNumberOfItemsWithLimitParameter(): void
    {
        $this->client->loginUser($this->getUser('admin'), 'login');

        // On charge les fixtures pour le test avec notre méthode en tête
        $this->databaseTool->loadAliceFixture([
            __DIR__ . '/Fixtures/ArticleFixture.yaml'
        ]);

        $this->client->request('GET', '/api/admin/articles?limit=1');

        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertCount(1, $response['items']);
        $this->assertEquals(12, $response['meta']['pages']);
    }

    // Test pour vérifier que l'endpoint retourne une erreur 404 quand le paramètre limit est négatif
    public function testIndexEndPointValidateErrorWhenLimitIsNegative(): void
    {
        $this->client->loginUser($this->getUser('admin'), 'login');


        $this->client->request('GET', '/api/admin/articles?limit=-1', server: [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('limit: This value should be positive.',$response['detail']);
    }

    // Test pour vérifier que l'endpoint retourne une erreur 404 quand le paramètre page est négatif
    public function testIndexEndPointValidateErrorWhenPageIsNegative(): void
    {
        $this->client->loginUser($this->getUser('admin'), 'login');


        $this->client->request('GET', '/api/admin/articles?page=-1', server: [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('page: This value should be positive.',$response['detail']);
    }

    // Test pour vérifier que l'endpoint retourne le bon premier article quand le paramètre page est changé
    public function testINdexEndPointValidateFirstItemWhenPageIsChanged(): void
    {
        $this->client->loginUser($this->getUser('admin'), 'login');
        $this->databaseTool->loadAliceFixture([
            __DIR__ . '/Fixtures/ArticleFixture.yaml'
        ]);

        $this->client->request('GET', '/api/admin/articles?page=2');

        // On récupère la réponse
        $response = json_decode($this->client->getResponse()->getContent(), true);

        // On vérifie que le premier article est bien le 7ème
        $this->assertEquals('Article 7', $response['items'][0]['title']);
    }

    // Test pour vérifier que l'endpoint retourne une erreur 401 quand aucun utilisateur n'est connecté
    public function testCreateEndpointWithNoConnectedUser(): void
    {
        $this->client->request('POST', '/api/admin/articles');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    // Test pour vérifier que l'endpoint retourne une erreur 403 quand un utilisateur connecté n'est pas admin
    public function testCreateEndpointWithConnectedUser(): void
    {
        $this->client->loginUser($this->getUser('user'), 'login');
        $this->client->request('POST', '/api/admin/articles');
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    // Test pour vérifier que l'endpoint retourne une erreur 201 quand un utilisateur connecté est admin
    public function testCreateEndpointWithConnectedAdminUser(): void
    {
        // Pas besoin de préciser admin car c'est le role par defaut
        $user = $this->getUser();
        
        $this->client->loginUser($user, 'login');
        $this->client->request('POST', '/api/admin/articles', [
            'title' => 'Article de test',
            'content' => 'Description de test',
            'shortContent' => 'Description courte de test',
            'user' => $user->getId(),
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    public function testCreateEndpointWithValidationInBDD(): void
    {
        // Pas besoin de préciser admin car c'est le role par defaut
        $user = $this->getUser();
        
        $this->client->loginUser($user, 'login');
        $this->client->request('POST', '/api/admin/articles', [
            'title' => 'Article de test',
            'content' => 'Description de test',
            'shortContent' => 'Description courte de test',
            'user' => $user->getId(),
        ]);

        $article = self::getContainer()->get(ArticleRepository::class)->findOneBy(['title' => 'Article de test']);
        $this->assertNotNull($article);
    }

    public function testUpdateEndPointWithConnectedAdminUser(): void
    {
        $user = $this->getUser();
        
        $this->client->loginUser($user, 'login');
        $this->databaseTool->loadAliceFixture([
            __DIR__ . '/Fixtures/ArticleFixture.yaml'
        ]);

        $article = self::getContainer()->get(ArticleRepository::class)->findOneBy(['title' => 'Article 1']);
        $this->client->request('PATCH', "/api/admin/articles/{$article->getId()}", [
            'title' => 'Article modifié'
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $article = self::getContainer()->get(ArticleRepository::class)->findOneBy(['title' => 'Article modifié']);
        $this->assertNotNull($article);
    }
}