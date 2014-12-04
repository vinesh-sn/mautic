<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticAddon\MauticSocialBundle\Integration;
use Mautic\AddonBundle\Integration\AbstractIntegration;

/**
 * Class FoursquareIntegration
 */
class FoursquareIntegration extends AbstractIntegration
{
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'Foursquare';
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority()
    {
        return 2;
    }

    /**
     * {@inheritdoc}
     */
    public function getIdentifierFields()
    {
        return array(
            'email',
            'twitter', //foursquare allows searching directly by twitter handle
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthenticationUrl()
    {
        return 'https://foursquare.com/oauth2/authenticate';
    }

    /**
     * {@inheritdoc}
     */
    public function getAccessTokenUrl()
    {
        return 'https://foursquare.com/oauth2/access_token';
    }

    /**
     * {@inheritdoc}
     */
    public function getRequiredKeyFields()
    {
        return array(
            'clientId'      => 'mautic.integration.keyfield.clientid',
            'clientSecret'  => 'mautic.integration.keyfield.clientsecret'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthenticationType()
    {
        return 'oauth2';
    }

    /**
     * @param string $endpoint
     * @param string $m
     *
     * @return string
     */
    public function getApiUrl($endpoint, $m = 'foursquare')
    {
        $keys = $this->settings->getApiKeys();
        $token = (isset($keys['access_token'])) ? $keys['access_token'] : '';
        return "https://api.foursquare.com/v2/$endpoint?v=20140806&m={$m}&oauth_token={$token}";
    }

    /**
     * Get public data
     *
     * @param $identifier
     * @param $socialCache
     *
     * @return array
     */
    public function getUserData($identifier, &$socialCache)
    {
        if ($id = $this->getUserId($identifier, $socialCache)) {
            $url  = $this->getApiUrl("users/{$id}");
            $data = $this->makeCall($url);
            if (!empty($data) && isset($data->response->user)) {
                $result = $data->response->user;
                $socialCache['profile'] = $this->matchUpData($result);
                if (isset($result->photo)) {
                    $socialCache['profile']['profileImage'] = $result->photo->prefix . '300x300' . $result->photo->suffix;
                }
                $socialCache['profile']['profileHandle'] = $id;
            }
        }
    }

    /**
     * {@inheritdoc}
     *
     * @param $identifier
     * @param $socialCache
     *
     * @return array|void
     */
    public function getPublicActivity($identifier, &$socialCache)
    {
        if ($id = $this->getUserId($identifier, $socialCache)) {
            $activity = array(
                //'mayorships' => array(),
                'tips'       => array(),
                //'lists'      => array()
            );

            /*
            //mayorships
            $url  = $this->getApiUrl("users/{$id}/mayorships");
            $data = $this->makeCall($url);

            if (isset($data->response->mayorships) && count($data->response->mayorships->items)) {
                $limit = 5;
                foreach ($data->response->mayorships->items as $m) {
                    if (empty($limit)) {
                        break;
                    }
                    //find main category of venue
                    $category = '';
                    foreach ($m->venue->categories as $c) {
                        if ($c->primary) {
                            $category = $c->name;
                            break;
                        }
                    }
                    $contact = (!empty($m->contact->formattedPhone)) ? $m->contact->formattedPhone : '';
                    $activity['mayorships'][] = array(
                        'venueName'     => $m->venue->name,
                        'venueLocation' => $m->venue->location->formattedAddress,
                        'venueContact'  => $contact,
                        'venueCategory' => $category
                    );
                    $limit--;
                }
            }
            */

            //tips
            $url  = $this->getApiUrl("users/{$id}/tips") . "&limit=5&sort=recent";
            $data = $this->makeCall($url);

            if (isset($data->response->tips) && count($data->response->tips->items)) {
                foreach ($data->response->tips->items as $t) {
                    //find main category of venue
                    $category = '';
                    foreach ($t->venue->categories as $c) {
                        if ($c->primary) {
                            $category = $c->name;
                            break;
                        }
                    }
                    $contact = (!empty($t->contact->formattedPhone)) ? $t->contact->formattedPhone : '';
                    $activity['tips'][] = array(
                        'createdAt'     => $t->createdAt,
                        'tipText'       => $t->text,
                        'tipUrl'        => $t->canonicalUrl,
                        'venueName'     => $t->venue->name,
                        'venueLocation' => $t->venue->location->formattedAddress,
                        'venueContact'  => $contact,
                        'venueCategory'  => $category
                    );
                }
            }

            /*
            //lists
            $url  = $this->getApiUrl("users/{$id}/lists") . "&limit=5&group=created";
            $data = $this->makeCall($url);

            if (isset($data->response->lists) && count($data->response->lists->items)) {
                foreach ($data->response->lists->items as $l) {
                    if (!$l->listItems->count) {
                        continue;
                    }

                    $item = array(
                        'listName'        => $l->name,
                        'listDescription' => $l->description,
                        'listUrl'         => $l->canonicalUrl,
                        'listCreatedAt'   => (isset($l->createdAt)) ? $l->createdAt : '',
                        'listUpdatedAt'   => (isset($l->updatedAt)) ? $l->updatedAt : '',
                        'listItems'       => array()
                    );

                    //get a sample of the list items
                    $url      = "https://api.foursquare.com/v2/lists/{$l->id}?limit=5&sort=recent&v=20140719&oauth_token={$keys['access_token']}";
                    $listData = $this->makeCall($url);

                    if (isset($listData->response->list->listItems) && count($listData->response->list->listItems->items)) {
                        foreach ($listData->response->list->listItems->items as $li) {
                            //find main category of venue
                            $category = '';
                            foreach ($li->venue->categories as $c) {
                                if ($c->primary) {
                                    $category = $c->name;
                                    break;
                                }
                            }
                            $contact = (!empty($li->contact->formattedPhone)) ? $li->contact->formattedPhone : '';

                            $item['listItems'][] = array(
                                'createdAt'     => $li->createdAt,
                                'venueName'     => $li->venue->name,
                                'venueLocation' => $li->venue->location->formattedAddress,
                                'venueContact'  => $contact,
                                'venueCategory'  => $category
                            );
                        }
                    }

                    $activity['lists'][] = $item;
                }
            }
            */

            if (!empty($activity)) {
                $socialCache['activity'] = $activity;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getErrorsFromResponse($response)
    {
        if (is_object($response) && isset($response->meta->errorDetail)) {
            return $response->meta->errorDetail . ' (' . $response->meta->code . ')';
        }

        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function matchFieldName($field, $subfield = '')
    {
        if ($field == "contact" && in_array($subfield, array('facebook', 'twitter'))) {
            return $subfield . 'ProfileHandle';
        }

        return parent::matchFieldName($field, $subfield);
    }

    /**
     * {@inheritdoc}
     */
    public function getAvailableFields($silenceExceptions = true)
    {
        return array(
            "profileHandle" => array("type" => "string"),
            "firstName"     => array("type" => "string"),
            "lastName"      => array("type" => "string"),
            "gender"        => array("type" => "string"),
            "homeCity"      => array("type" => "string"),
            "bio"           => array("type" => "string"),
            "contact"       => array(
                "type"   => "object",
                "fields" => array(
                    "twitter",
                    "facebook",
                    "phone"
                )
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedFeatures()
    {
        return array(
            'public_profile',
            'public_activity'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getUserId($identifier, &$socialCache)
    {
        if (!empty($socialCache['id'])) {
            return $socialCache['id'];
        } elseif (empty($identifier)) {
            return false;
        }

        $cleaned = $this->cleanIdentifier($identifier);

        if (!is_array($cleaned)) {
            $cleaned = array($cleaned);
        }

        $keys  = $this->settings->getApiKeys();

        if (!empty($keys['access_token'])) {
            foreach ($cleaned as $type => $c) {
                $url  = $this->getApiUrl("users/search") . "&{$type}={$c}";
                $data = $this->makeCall($url);
                if (!empty($data) && isset($data->response->results) && count($data->response->results)) {
                    $socialCache['id'] = $data->response->results[0]->id;
                    return $socialCache['id'];
                }
            }
        }

        return false;
    }
}