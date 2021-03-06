<?php
/**
 * @copyright Copyright (C) 2017 Opencontent Società Cooperativa,  All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 * @package ocsdc
 */

namespace Tests\Flows\Operatore;

use AppBundle\Entity\Allegato;
use AppBundle\Entity\AsiloNido;
use AppBundle\Entity\AttestazioneAnagrafica;
use AppBundle\Entity\CertificatoNascita;
use AppBundle\Entity\ComponenteNucleoFamiliare;
use AppBundle\Entity\Ente;
use AppBundle\Entity\ListeElettorali;
use AppBundle\Entity\OperatoreUser;
use AppBundle\Entity\Pratica;
use AppBundle\Entity\Servizio;
use AppBundle\Entity\User;
use AppBundle\Services\CPSUserProvider;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Tests\AppBundle\Base\AbstractAppTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Class AllegatoFlowOperatoreTest
 */
class AllegatoFlowOperatoreTest extends AbstractAppTestCase
{
    /**
     * @var CPSUserProvider
     */
    protected $userProvider;

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        parent::setUp();

        system('rm -rf '.__DIR__."/../../../var/uploads/pratiche/allegati/*");

        $this->userProvider = $this->container->get('ocsdc.cps.userprovider');
        $this->em->getConnection()->executeQuery('DELETE FROM servizio_erogatori')->execute();
        $this->em->getConnection()->executeQuery('DELETE FROM erogatore_ente')->execute();
        $this->em->getConnection()->executeQuery('DELETE FROM ente_asili')->execute();
        $this->cleanDb(ComponenteNucleoFamiliare::class);
        $this->cleanDb(Pratica::class);
        $this->cleanDb(Allegato::class);
        $this->cleanDb(Servizio::class);
        $this->cleanDb(AsiloNido::class);
        $this->cleanDb(OperatoreUser::class);
        $this->cleanDb(Ente::class);
        $this->cleanDb(User::class);
    }

    public function servicesDataProvider()
    {
        return array(
            array('Attestazione Angarafica', AttestazioneAnagrafica::class, 'ocsdc.form.flow.attestazioneanagrafica', 'ocsdc.form.flow.attestazioneanagraficaoperatore', 'upload_attestazione_anagrafica'),
            array('Certificato Nascita', CertificatoNascita::class, 'ocsdc.form.flow.certificatonascita', 'ocsdc.form.flow.certificatonascitaoperatore', 'upload_certificato_nascita'),
            array('Liste Elettorali', ListeElettorali::class, 'ocsdc.form.flow.listeelettorali', 'ocsdc.form.flow.listeelettoralioperatore', 'upload_attestazione_liste_elettorali'),
            array('Stato di Famiglia', CertificatoNascita::class, 'ocsdc.form.flow.statofamiglia', 'ocsdc.form.flow.statofamigliaoperatore', 'upload_stato_famiglia'),
        );
    }

    /**
     * @dataProvider servicesDataProvider
     *
     * @param $title
     * @param $fqcn
     * @param $flow
     * @param $flowOperatore
     */
    public function testICannotApproveRequestWithoutUploadAFileAsOperatore($title, $fqcn, $flow, $flowOperatore, $prefix)
    {
        $password = 'pa$$word';
        $username = 'username';
        $operatore = $this->createOperatoreUser($username, $password);
        //create an ente
        $ente = $this->createEnti()[0];
        $erogatore = $this->createErogatoreWithEnti([$ente]);

        $servizio = $this->createServizioWithAssociatedErogatori([$erogatore], $title, $fqcn, $flow, $flowOperatore);
        $user = $this->createCPSUser();

        $pratica = $this->createPratica($user, $operatore, Pratica::STATUS_PENDING, $erogatore, $servizio);

        $detailPraticaUrl = $this->router->generate('operatori_show_pratica', ['pratica' => $pratica->getId()]);
        $elaboraPraticaUrl = $this->router->generate('operatori_elabora_pratica', ['pratica' => $pratica->getId()]);

        $this->client->request('GET', $detailPraticaUrl, array(), array(), array(
            'PHP_AUTH_USER' => $username,
            'PHP_AUTH_PW' => $password,
        ));

        $this->assertContains($elaboraPraticaUrl, $this->client->getResponse()->getContent());

        $this->client->request('GET', $elaboraPraticaUrl, array(), array(), array(
            'PHP_AUTH_USER' => $username,
            'PHP_AUTH_PW' => $password,
        ));
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode(), "Unexpected HTTP status code");

        // testare il file
        $crawler = $this->client->getCrawler();
        $nextButton = $this->translator->trans('button.next', [], 'CraueFormFlowBundle');
        $finishButton = $this->translator->trans('button.finish', [], 'CraueFormFlowBundle');

        $this->approvaPratica($crawler, $nextButton, $form);

        $form = $crawler->selectButton($nextButton)->form();

        $crawler = $this->client->submit($form);
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode(), "Unexpected HTTP status code");

        $msg = trim($crawler->filter('.alert-danger ul')->first()->text());
        $this->assertEquals($msg, "Il campo file è richiesto", "File obbligatorio");
    }


    /**
     * @dataProvider servicesDataProvider
     *
     * @param $title
     * @param $fqcn
     * @param $flow
     * @param $flowOperatore
     */
    public function testICanRejectRequestAsOperatore($title, $fqcn, $flow, $flowOperatore, $prefix){

        $password = 'pa$$word';
        $username = 'username';
        $operatore = $this->createOperatoreUser($username, $password);
        //create an ente
        $ente = $this->createEnti()[0];
        $erogatore = $this->createErogatoreWithEnti([$ente]);

        $servizio = $this->createServizioWithAssociatedErogatori([$erogatore], $title, $fqcn, $flow, $flowOperatore);
        $user = $this->createCPSUser();

        $pratica = $this->createPratica($user, $operatore, Pratica::STATUS_PENDING, $erogatore, $servizio);

        $detailPraticaUrl = $this->router->generate('operatori_show_pratica', ['pratica' => $pratica->getId()]);
        $elaboraPraticaUrl = $this->router->generate('operatori_elabora_pratica', ['pratica' => $pratica->getId()]);

        $this->client->request('GET', $detailPraticaUrl, array(), array(), array(
            'PHP_AUTH_USER' => $username,
            'PHP_AUTH_PW' => $password,
        ));

        $this->assertContains($elaboraPraticaUrl, $this->client->getResponse()->getContent());

        $this->client->request('GET', $elaboraPraticaUrl, array(), array(), array(
            'PHP_AUTH_USER' => $username,
            'PHP_AUTH_PW' => $password,
        ));
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode(), "Unexpected HTTP status code");

        // testare il file
        $crawler = $this->client->getCrawler();
        $nextButton = $this->translator->trans('button.next', [], 'CraueFormFlowBundle');
        $finishButton = $this->translator->trans('button.finish', [], 'CraueFormFlowBundle');

        $this->rifiutaPratica($crawler, $nextButton, $form);
        $pratica = $this->em->getRepository(Pratica::class)->find($pratica->getId());
        $this->assertFalse($pratica->getEsito());

        // Step 2: Skypped

        // Step 3: Upload risposta firmata
        $form = $crawler->selectButton($finishButton)->form();
        $crawler = $this->client->submit($form);
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode(), "Unexpected HTTP status code");

        $this->assertRegExp('/Scarica la risposta dal seguente link/',$crawler->text());

        //TODO: test we actually get the pdf

        $form = $crawler->selectButton($finishButton)->form();
        copy(__DIR__.'/../../AppBundle/Assets/AttoFirmatoDiProva.pdf.p7m', __DIR__.'/run_test2.pdf.p7m');
        $file = new UploadedFile(__DIR__.'/run_test2.pdf.p7m', 'test.pdf.p7m', null, null, null, true);

        $form['upload_risposta_firmata[allegati_operatore][add]']->upload($file);
        $crawler = $this->client->submit($form);
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode(), "Unexpected HTTP status code");

        $msg = trim($crawler->filter('.alert-danger ul')->first()->text());
        $this->assertEquals($msg, "Il file è stato caricato correttamente", "File non caricato");


        $form = $crawler->selectButton($finishButton)->form();
        $this->client->submit($form);
        $this->assertEquals(Response::HTTP_FOUND, $this->client->getResponse()->getStatusCode(), "Unexpected HTTP status code");
        $this->client->followRedirect();

        $this->em->detach($pratica);
        /** @var Pratica $pratica */
        $pratica = $this->em->getRepository('AppBundle:Pratica')->findOneById($pratica->getId());
        $this->assertEquals(Pratica::STATUS_CANCELLED_WAITALLEGATIOPERATORE, $pratica->getStatus());
    }

    /**
     * @dataProvider servicesDataProvider
     *
     * @param $title
     * @param $fqcn
     * @param $flow
     * @param $flowOperatore
     */
    public function testICanApproveRequestAsOperatore($title, $fqcn, $flow, $flowOperatore, $prefix)
    {
        $password = 'pa$$word';
        $username = 'username';
        //create an ente
        $ente = $this->createEnti()[0];
        $erogatore = $this->createErogatoreWithEnti([$ente]);
        $operatore = $this->createOperatoreUser($username, $password, $ente);

        $servizio = $this->createServizioWithAssociatedErogatori(array($erogatore), $title, $fqcn, $flow, $flowOperatore);
        $user = $this->createCPSUser();

        $pratica = $this->createPratica($user, $operatore, Pratica::STATUS_PENDING, $erogatore, $servizio);

        $mockMailer = $this->setupSwiftmailerMock([$user, $operatore]);
        static::$kernel->setKernelModifier(function (KernelInterface $kernel) use ($mockMailer) {
            $kernel->getContainer()->set('swiftmailer.mailer.default', $mockMailer);
        });

        $detailPraticaUrl = $this->router->generate('operatori_show_pratica', ['pratica' => $pratica->getId()]);
        $elaboraPraticaUrl = $this->router->generate('operatori_elabora_pratica', ['pratica' => $pratica->getId()]);
        $this->client->request('GET', $detailPraticaUrl, array(), array(), array(
            'PHP_AUTH_USER' => $username,
            'PHP_AUTH_PW' => $password,
        ));

        $this->assertContains($elaboraPraticaUrl, $this->client->getResponse()->getContent());

        $this->client->request('GET', $elaboraPraticaUrl, array(), array(), array(
            'PHP_AUTH_USER' => $username,
            'PHP_AUTH_PW' => $password,
        ));
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode(), "Unexpected HTTP status code");

        // testare il file
        $crawler = $this->client->getCrawler();
        $nextButton = $this->translator->trans('button.next', [], 'CraueFormFlowBundle');
        $uploadRispostaButton = $this->translator->trans('operatori.allega_risposta_firmata');
        $finishButton = $this->translator->trans('button.finish', [], 'CraueFormFlowBundle');

        // Step 1: Approva o rigetta
        $this->assertNull($pratica->getEsito());
        $this->approvaPratica($crawler, $nextButton, $form);
        $pratica = $this->em->getRepository(Pratica::class)->find($pratica->getId());
        $this->assertTrue($pratica->getEsito());

        //$this->addAllegatoOperatore($crawler, $nextButton, $form);

        // Step 2: Upload allegato operatore
        $form = $crawler->selectButton($nextButton)->form();
        copy(__DIR__.'/test.pdf', __DIR__.'/run_test.pdf');
        $file = new UploadedFile(__DIR__.'/run_test.pdf', 'test.pdf', null, null, null, true);

        $form[$prefix . '[allegati_operatore][add]']->upload($file);
        $crawler = $this->client->submit($form);
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode(), "Unexpected HTTP status code");

        //print_r($crawler->filter('.alert-danger'));
        $msg = trim($crawler->filter('.alert-danger ul')->first()->text());
        $this->assertEquals("Il file è stato caricato correttamente", $msg, "File non caricato");

        //$radio = $crawler->filter("input[type=radio]");

        // Step 3: Upload risposta firmata
        $form = $crawler->selectButton($nextButton)->form();
        $crawler = $this->client->submit($form);
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode(), "Unexpected HTTP status code");

        $this->assertRegExp('/Scarica la risposta dal seguente link/',$crawler->text());

        //TODO: test we actually get the pdf

        $form = $crawler->selectButton($finishButton)->form();
        copy(__DIR__.'/../../AppBundle/Assets/AttoFirmatoDiProva.pdf.p7m', __DIR__.'/run_test2.pdf.p7m');
        $file = new UploadedFile(__DIR__.'/run_test2.pdf.p7m', 'test.pdf.p7m', null, null, null, true);

        $form['upload_risposta_firmata[allegati_operatore][add]']->upload($file);
        $crawler = $this->client->submit($form);
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode(), "Unexpected HTTP status code");

        $msg = trim($crawler->filter('.alert-danger ul')->first()->text());
        $this->assertEquals($msg, "Il file è stato caricato correttamente", "File non caricato");


        $form = $crawler->selectButton($finishButton)->form();
        $this->client->submit($form);
        $this->assertEquals(Response::HTTP_FOUND, $this->client->getResponse()->getStatusCode(), "Unexpected HTTP status code");
        $this->client->followRedirect();
        /** @var Pratica $pratica */
        $this->em->detach($pratica);
        $pratica = $this->em->getRepository('AppBundle:Pratica')->findOneById($pratica->getId());
        $this->assertEquals(Pratica::STATUS_COMPLETE_WAITALLEGATIOPERATORE, $pratica->getStatus());

    }

    /**
     * @param Crawler $crawler
     * @param $nextButton
     * @param $form
     */
    protected function approvaPratica(&$crawler, $nextButton, &$form)
    {
        $form = $crawler->selectButton($nextButton)->form(array(
            'approva_o_rigetta[esito]' => '1',
            'approva_o_rigetta[motivazioneEsito]' => 'Motivazione approvazione'
        ));
        $crawler = $this->client->submit($form);
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode(), "Unexpected HTTP status code");
    }

    /**
     * @param Crawler $crawler
     * @param $nextButton
     * @param $form
     */
    protected function rifiutaPratica(&$crawler, $nextButton, &$form)
    {
        $form = $crawler->selectButton($nextButton)->form(array(
            'approva_o_rigetta[esito]' => '0',
            'approva_o_rigetta[motivazioneEsito]' => 'Motivazione rifiuto'
        ));
        $crawler = $this->client->submit($form);
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode(), "Unexpected HTTP status code");
    }

}
