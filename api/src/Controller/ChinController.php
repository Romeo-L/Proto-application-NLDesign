<?php

// src/Controller/ProcessController.php

namespace App\Controller;

use Conduction\CommonGroundBundle\Service\ApplicationService;
//use App\Service\RequestService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use function GuzzleHttp\Promise\all;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Annotation\Route;

/**
 * The Procces test handles any calls that have not been picked up by another test, and wel try to handle the slug based against the wrc.
 *
 * Class ProcessController
 *
 * @Route("/chin")
 */
class ChinController extends AbstractController
{
    /**
     * @Route("/checkin/user")
     * @Template
     */
    public function checkinUserAction(Session $session, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params, string $slug = 'home')
    {
        $variables = [];
        $variables['checkins'] = $commonGroundService->getResourceList(['component' => 'chin', 'type' => 'checkins'], ['person' => $this->getUser()->getPerson(), 'order[dateCreated]' => 'desc'])['hydra:member'];

        return $variables;
    }

    /**
     * @Route("/checkin/organisation")
     * @Template
     */
    public function checkinOrganizationAction(Session $session, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params, string $slug = 'home')
    {
        $variables = [];
        $variables['checkins'] = $commonGroundService->getResourceList(['component' => 'chin', 'type' => 'checkins'], ['person' => $this->getUser()->getOrganization(), 'order[dateCreated]' => 'desc'])['hydra:member'];

        return $variables;
    }

    /**
     * @Route("/nodes/user")
     * @Template
     */
    public function nodesUserAction(Session $session, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params, string $slug = 'home')
    {
        $variables = [];
        $variables['nodes'] = $commonGroundService->getResourceList(['component' => 'chin', 'type' => 'nodes'], ['person' => $this->getUser()->getPerson(), 'order[dateCreated]' => 'desc'])['hydra:member'];

        return $variables;
    }

    /**
     * @Route("/nodes/organization")
     * @Template
     */
    public function nodesOrganizationAction(Session $session, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params, string $slug = 'home')
    {
        $variables = [];
        $variables['places'] = $commonGroundService->getResourceList(['component' => 'lc', 'type' => 'places'])['hydra:member'];
        $variables['organizations'] = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'organizations'])['hydra:member'];
        $variables['nodes'] = $commonGroundService->getResourceList(['component' => 'chin', 'type' => 'nodes'])['hydra:member'];

        if ($request->isMethod('POST')) {
            $resource = $request->request->all();

            $commonGroundService->saveResource($resource, (['component' => 'chin', 'type' => 'nodes']));

            return $this->redirect($this->generateUrl('app_chin_nodesorganization'));
        }

        return $variables;
    }

    /**
     * @Route("/nodes/create")
     * @Template
     */
    public function nodesCreateAction(Session $session, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params, string $slug = 'home')
    {
        $variables = $applicationService->getVariables();

        // Lets provide this data to the template
        $variables['query'] = $request->query->all();
        $variables['post'] = $request->request->all();

        // Lets see if there is a post to procces
        if ($request->isMethod('POST')) {
            $resource = $request->request->all();

            //create the node
            if (array_key_exists('user', $variables) and is_array($variables['user'])) {
                if (array_key_exists('organization', $variables['user'])) {
                    $resource['organization'] = $commonGroundService->getResource(['component' => 'kvk', 'type' => 'companies', 'id' => $variables['user']['organization']]); //get the organization of this 'medewerker'
                }
            } else {
                $resource['organization'] = $commonGroundService->cleanUrl(['component' => 'wrc', 'type' => 'organizations', 'id' => '4d1eded3-fbdf-438f-9536-8747dd8ab591']);
            }
            $resource['place'] = $commonGroundService->cleanUrl(['component' => 'lc', 'type' => 'places', 'id' => 'db91b486-cbbb-47aa-9771-77862fda6c15']); //the selected place for this qr code
            $resource['passthroughUrl'] = 'https://zuid-drecht.nl';
            $commonGroundService->createResource($resource, ['component' => 'chin', 'type' => 'nodes']);

            return $this->redirectToRoute('app_default_index');
        }

        return $variables;
    }

    /**
     * This function shows all available locations.
     *
     * @Route("/")
     * @Template
     */
    public function indexAction(Session $session, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params)
    {
        $variables = $applicationService->getVariables();
        $variables['resources'] = $commonGroundService->getResourceList(['component' => 'cmc', 'type' => 'contact_moments'], ['receiver' => $this->getUser()->getPerson()])['hydra:member'];

        return $variables;
    }

    /**
     * This function will kick of the suplied proces with given values.
     *
     * @Route("/checkin/{code}")
     * @Template
     */
    public function checkinAction(Session $session, $code = null, Request $request, FlashBagInterface $flash, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params)
    {
        $session->remove('newcheckin');

        $application = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'applications', 'id' => getenv('APP_ID')]);

        $variables = [];
        $createCheckin = $request->request->get('createCheckin');

        // Fallback options of establishing
        if (!$code) {
            $code = $request->query->get('code');
        }
        if (!$code) {
            $code = $request->request->get('code');
        }
        if (!$code) {
            $code = $session->get('code');
        }

        if ($code) {
            $session->set('code', $code);
            $variables['code'] = $code;
            $variables['resources'] = $commonGroundService->getResourceList(['component' => 'chin', 'type' => 'nodes'], ['reference' => $code])['hydra:member'];
            if (count($variables['resources']) > 0) {
                $variables['resource'] = $variables['resources'][0];
            }
        }

        // Alleen afgaan bij post EN ingelogde gebruiker

        if ($request->isMethod('POST') && $this->getUser() && $createCheckin == 'true') {

            //update person
            $node = $request->request->get('node');
            $name = $request->request->get('name');
            $email = $request->request->get('email');
            $tel = $request->request->get('tel');

            $person = $commonGroundService->getResource($this->getUser()->getPerson());
            $user = $commonGroundService->getResourceList(['component' => 'uc', 'type' => 'users'], ['person' => $person['@id']])['hydra:member'];
            $user = $user[0];

            $emailResource = $person['emails'][0];
            $emailResource['email'] = $email;
            $commonGroundService->updateResource($emailResource);

            $telephoneResource = $person['telephones'][0];
            $telephoneResource['telephone'] = $tel;
            $commonGroundService->updateResource($telephoneResource);

            //create check-in
            $checkIn = [];
            $checkIn['node'] = $node;
            $checkIn['person'] = $person['@id'];
            $checkIn['userUrl'] = $user['@id'];

            $checkIn = $commonGroundService->createResource($checkIn, ['component' => 'chin', 'type' => 'checkins']);

            // If the passthroughUrl is to Zuid-Drecht we will ignore it for testing purposes
            $isUrlToZD = strpos($node['passthroughUrl'], 'zuid-drecht');
            if ($isUrlToZD === false) {
                return $this->redirect($node['passthroughUrl']);
            }

            $session->set('newcheckin', true);

            if (isset($application['defaultConfiguration']['configuration']['userPage'])) {
                return $this->redirect('/'.$application['defaultConfiguration']['configuration']['userPage']);
            } else {
                return $this->redirect($this->generateUrl('app_default_index'));
            }
        } elseif ($request->isMethod('POST') && $createCheckin == 'true') {
            $node = $request->request->get('node');
            $firstName = $request->request->get('firstName');
            $additionalName = $request->request->get('additionalName');
            $lastName = $request->request->get('lastName');
            $email = $request->request->get('email');
            $tel = $request->request->get('tel');

            $emailObject['email'] = $email;
            $emailObject = $commonGroundService->createResource($emailObject, ['component' => 'cc', 'type' => 'emails']);

            $telObject['telephone'] = $tel;
            $telObject = $commonGroundService->createResource($telObject, ['component' => 'cc', 'type' => 'telephones']);

            $person['givenName'] = $firstName;
            $person['additionalName'] = $additionalName;
            $person['familyName'] = $lastName;
            $person['emails'][] = $emailObject['@id'];
            $person['telephones'][] = $telObject['@id'];
            $person = $commonGroundService->createResource($person, ['component' => 'cc', 'type' => 'people']);

            $checkIn['node'] = $node;
            $checkIn['person'] = $person['@id'];

            $checkIn = $commonGroundService->createResource($checkIn, ['component' => 'chin', 'type' => 'checkins']);

            $node = $commonGroundService->getResource($node);

            // If the passthroughUrl is to Zuid-Drecht we will ignore it for testing purposes
            $isUrlToZD = strpos($node['passthroughUrl'], 'zuid-drecht');
            if ($isUrlToZD === false) {
                return $this->redirect($node['passthroughUrl']);
            }

            $session->set('newcheckin', true);
            $session->set('person', $person);

            if (isset($application['defaultConfiguration']['configuration']['userPage'])) {
                return $this->redirect('/'.$application['defaultConfiguration']['configuration']['userPage']);
            } else {
                return $this->redirect($this->generateUrl('app_default_index'));
            }
        }

        return $variables;
    }

    /**
     * @Route("/onboarding")
     * @Template
     */
    public function onboardingAction(Session $session, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params)
    {
        $variables = $applicationService->getVariables();

        return $variables;
    }
}
