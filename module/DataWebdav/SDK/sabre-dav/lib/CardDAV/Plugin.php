<?php

namespace Sabre\CardDAV;

use Sabre\DAV;
use Sabre\DAV\Exception\ReportNotSupported;
use Sabre\DAV\Xml\Property\LocalHref;
use Sabre\DAVACL;
use Sabre\HTTP;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;
use Sabre\VObject;


class Plugin extends DAV\ServerPlugin {

    
    const ADDRESSBOOK_ROOT = 'addressbooks';

    
    const NS_CARDDAV = 'urn:ietf:params:xml:ns:carddav';

    
    public $directories = [];

    
    protected $server;

    
    protected $maxResourceSize = 10000000;

    
    function initialize(DAV\Server $server) {

        
        $server->on('propFind',            [$this, 'propFindEarly']);
        $server->on('propFind',            [$this, 'propFindLate'], 150);
        $server->on('report',              [$this, 'report']);
        $server->on('onHTMLActionsPanel',  [$this, 'htmlActionsPanel']);
        $server->on('beforeWriteContent',  [$this, 'beforeWriteContent']);
        $server->on('beforeCreateFile',    [$this, 'beforeCreateFile']);
        $server->on('afterMethod:GET',     [$this, 'httpAfterGet']);

        $server->xml->namespaceMap[self::NS_CARDDAV] = 'card';

        $server->xml->elementMap['{' . self::NS_CARDDAV . '}addressbook-query'] = 'Sabre\\CardDAV\\Xml\\Request\\AddressBookQueryReport';
        $server->xml->elementMap['{' . self::NS_CARDDAV . '}addressbook-multiget'] = 'Sabre\\CardDAV\\Xml\\Request\\AddressBookMultiGetReport';

        
        $server->resourceTypeMapping['Sabre\\CardDAV\\IAddressBook'] = '{' . self::NS_CARDDAV . '}addressbook';
        $server->resourceTypeMapping['Sabre\\CardDAV\\IDirectory'] = '{' . self::NS_CARDDAV . '}directory';

        
        $server->protectedProperties[] = '{' . self::NS_CARDDAV . '}supported-address-data';
        $server->protectedProperties[] = '{' . self::NS_CARDDAV . '}max-resource-size';
        $server->protectedProperties[] = '{' . self::NS_CARDDAV . '}addressbook-home-set';
        $server->protectedProperties[] = '{' . self::NS_CARDDAV . '}supported-collation-set';

        $server->xml->elementMap['{http://calendarserver.org/ns/}me-card'] = 'Sabre\\DAV\\Xml\\Property\\Href';

        $this->server = $server;

    }

    
    function getFeatures() {

        return ['addressbook'];

    }

    
    function getSupportedReportSet($uri) {

        $node = $this->server->tree->getNodeForPath($uri);
        if ($node instanceof IAddressBook || $node instanceof ICard) {
            return [
                 '{' . self::NS_CARDDAV . '}addressbook-multiget',
                 '{' . self::NS_CARDDAV . '}addressbook-query',
            ];
        }
        return [];

    }


    
    function propFindEarly(DAV\PropFind $propFind, DAV\INode $node) {

        $ns = '{' . self::NS_CARDDAV . '}';

        if ($node instanceof IAddressBook) {

            $propFind->handle($ns . 'max-resource-size', $this->maxResourceSize);
            $propFind->handle($ns . 'supported-address-data', function() {
                return new Xml\Property\SupportedAddressData();
            });
            $propFind->handle($ns . 'supported-collation-set', function() {
                return new Xml\Property\SupportedCollationSet();
            });

        }
        if ($node instanceof DAVACL\IPrincipal) {

            $path = $propFind->getPath();

            $propFind->handle('{' . self::NS_CARDDAV . '}addressbook-home-set', function() use ($path) {
                return new LocalHref($this->getAddressBookHomeForPrincipal($path) . '/');
            });

            if ($this->directories) $propFind->handle('{' . self::NS_CARDDAV . '}directory-gateway', function() {
                return new LocalHref($this->directories);
            });

        }

        if ($node instanceof ICard) {

                                                $propFind->handle('{' . self::NS_CARDDAV . '}address-data', function() use ($node) {
                $val = $node->get();
                if (is_resource($val))
                    $val = stream_get_contents($val);

                return $val;

            });

        }

    }

    
    function report($reportName, $dom, $path) {

        switch ($reportName) {
            case '{' . self::NS_CARDDAV . '}addressbook-multiget' :
                $this->server->transactionType = 'report-addressbook-multiget';
                $this->addressbookMultiGetReport($dom);
                return false;
            case '{' . self::NS_CARDDAV . '}addressbook-query' :
                $this->server->transactionType = 'report-addressbook-query';
                $this->addressBookQueryReport($dom);
                return false;
            default :
                return;

        }


    }

    
    protected function getAddressbookHomeForPrincipal($principal) {

        list(, $principalId) = \Sabre\HTTP\URLUtil::splitPath($principal);
        return self::ADDRESSBOOK_ROOT . '/' . $principalId;

    }


    
    function addressbookMultiGetReport($report) {

        $contentType = $report->contentType;
        $version = $report->version;
        if ($version) {
            $contentType .= '; version=' . $version;
        }

        $vcardType = $this->negotiateVCard(
            $contentType
        );

        $propertyList = [];
        $paths = array_map(
            [$this->server, 'calculateUri'],
            $report->hrefs
        );
        foreach ($this->server->getPropertiesForMultiplePaths($paths, $report->properties) as $props) {

            if (isset($props['200']['{' . self::NS_CARDDAV . '}address-data'])) {

                $props['200']['{' . self::NS_CARDDAV . '}address-data'] = $this->convertVCard(
                    $props[200]['{' . self::NS_CARDDAV . '}address-data'],
                    $vcardType
                );

            }
            $propertyList[] = $props;

        }

        $prefer = $this->server->getHTTPPrefer();

        $this->server->httpResponse->setStatus(207);
        $this->server->httpResponse->setHeader('Content-Type', 'application/xml; charset=utf-8');
        $this->server->httpResponse->setHeader('Vary', 'Brief,Prefer');
        $this->server->httpResponse->setBody($this->server->generateMultiStatus($propertyList, $prefer['return'] === 'minimal'));

    }

    
    function beforeWriteContent($path, DAV\IFile $node, &$data, &$modified) {

        if (!$node instanceof ICard)
            return;

        $this->validateVCard($data, $modified);

    }

    
    function beforeCreateFile($path, &$data, DAV\ICollection $parentNode, &$modified) {

        if (!$parentNode instanceof IAddressBook)
            return;

        $this->validateVCard($data, $modified);

    }

    
    protected function validateVCard(&$data, &$modified) {

                if (is_resource($data)) {
            $data = stream_get_contents($data);
        }

        $before = $data;

        try {

                                    if (substr($data, 0, 1) === '[') {
                $vobj = VObject\Reader::readJson($data);

                                                $data = $vobj->serialize();
                $modified = true;
            } else {
                $vobj = VObject\Reader::read($data);
            }

        } catch (VObject\ParseException $e) {

            throw new DAV\Exception\UnsupportedMediaType('This resource only supports valid vCard or jCard data. Parse error: ' . $e->getMessage());

        }

        if ($vobj->name !== 'VCARD') {
            throw new DAV\Exception\UnsupportedMediaType('This collection can only support vcard objects.');
        }

        $options = VObject\Node::PROFILE_CARDDAV;
        $prefer = $this->server->getHTTPPrefer();

        if ($prefer['handling'] !== 'strict') {
            $options |= VObject\Node::REPAIR;
        }

        $messages = $vobj->validate($options);

        $highestLevel = 0;
        $warningMessage = null;

                        foreach ($messages as $message) {

            if ($message['level'] > $highestLevel) {
                                $highestLevel = $message['level'];
                $warningMessage = $message['message'];
            }

            switch ($message['level']) {

                case 1 :
                                        $modified = true;
                    break;
                case 2 :
                                        break;
                case 3 :
                                        throw new DAV\Exception\UnsupportedMediaType('Validation error in vCard: ' . $message['message']);

            }

        }
        if ($warningMessage) {
            $this->server->httpResponse->setHeader(
                'X-Sabre-Ew-Gross',
                'vCard validation warning: ' . $warningMessage
            );

                        $data = $vobj->serialize();
            if (!$modified && strcmp($data, $before) !== 0) {
                                $modified = true;
            }
        }

                $vobj->destroy();
    }


    
    protected function addressbookQueryReport($report) {

        $depth = $this->server->getHTTPDepth(0);

        if ($depth == 0) {
            $candidateNodes = [
                $this->server->tree->getNodeForPath($this->server->getRequestUri())
            ];
            if (!$candidateNodes[0] instanceof ICard) {
                throw new ReportNotSupported('The addressbook-query report is not supported on this url with Depth: 0');
            }
        } else {
            $candidateNodes = $this->server->tree->getChildren($this->server->getRequestUri());
        }

        $contentType = $report->contentType;
        if ($report->version) {
            $contentType .= '; version=' . $report->version;
        }

        $vcardType = $this->negotiateVCard(
            $contentType
        );

        $validNodes = [];
        foreach ($candidateNodes as $node) {

            if (!$node instanceof ICard)
                continue;

            $blob = $node->get();
            if (is_resource($blob)) {
                $blob = stream_get_contents($blob);
            }

            if (!$this->validateFilters($blob, $report->filters, $report->test)) {
                continue;
            }

            $validNodes[] = $node;

            if ($report->limit && $report->limit <= count($validNodes)) {
                                break;
            }

        }

        $result = [];
        foreach ($validNodes as $validNode) {

            if ($depth == 0) {
                $href = $this->server->getRequestUri();
            } else {
                $href = $this->server->getRequestUri() . '/' . $validNode->getName();
            }

            list($props) = $this->server->getPropertiesForPath($href, $report->properties, 0);

            if (isset($props[200]['{' . self::NS_CARDDAV . '}address-data'])) {

                $props[200]['{' . self::NS_CARDDAV . '}address-data'] = $this->convertVCard(
                    $props[200]['{' . self::NS_CARDDAV . '}address-data'],
                    $vcardType,
                    $report->addressDataProperties
                );

            }
            $result[] = $props;

        }

        $prefer = $this->server->getHTTPPrefer();

        $this->server->httpResponse->setStatus(207);
        $this->server->httpResponse->setHeader('Content-Type', 'application/xml; charset=utf-8');
        $this->server->httpResponse->setHeader('Vary', 'Brief,Prefer');
        $this->server->httpResponse->setBody($this->server->generateMultiStatus($result, $prefer['return'] === 'minimal'));

    }

    
    function validateFilters($vcardData, array $filters, $test) {


        if (!$filters) return true;
        $vcard = VObject\Reader::read($vcardData);

        foreach ($filters as $filter) {

            $isDefined = isset($vcard->{$filter['name']});
            if ($filter['is-not-defined']) {
                if ($isDefined) {
                    $success = false;
                } else {
                    $success = true;
                }
            } elseif ((!$filter['param-filters'] && !$filter['text-matches']) || !$isDefined) {

                                $success = $isDefined;

            } else {

                $vProperties = $vcard->select($filter['name']);

                $results = [];
                if ($filter['param-filters']) {
                    $results[] = $this->validateParamFilters($vProperties, $filter['param-filters'], $filter['test']);
                }
                if ($filter['text-matches']) {
                    $texts = [];
                    foreach ($vProperties as $vProperty)
                        $texts[] = $vProperty->getValue();

                    $results[] = $this->validateTextMatches($texts, $filter['text-matches'], $filter['test']);
                }

                if (count($results) === 1) {
                    $success = $results[0];
                } else {
                    if ($filter['test'] === 'anyof') {
                        $success = $results[0] || $results[1];
                    } else {
                        $success = $results[0] && $results[1];
                    }
                }

            } 
                                    if ($test === 'anyof' && $success) {

                                $vcard->destroy();

                return true;
            }
            if ($test === 'allof' && !$success) {

                                $vcard->destroy();

                return false;
            }

        } 

                $vcard->destroy();

                                                return $test === 'allof';

    }

    
    protected function validateParamFilters(array $vProperties, array $filters, $test) {

        foreach ($filters as $filter) {

            $isDefined = false;
            foreach ($vProperties as $vProperty) {
                $isDefined = isset($vProperty[$filter['name']]);
                if ($isDefined) break;
            }

            if ($filter['is-not-defined']) {
                if ($isDefined) {
                    $success = false;
                } else {
                    $success = true;
                }

                        } elseif (!$filter['text-match'] || !$isDefined) {

                $success = $isDefined;

            } else {

                $success = false;
                foreach ($vProperties as $vProperty) {
                                                            $success = DAV\StringUtil::textMatch($vProperty[$filter['name']]->getValue(), $filter['text-match']['value'], $filter['text-match']['collation'], $filter['text-match']['match-type']);
                    if ($success) break;
                }
                if ($filter['text-match']['negate-condition']) {
                    $success = !$success;
                }

            } 
                                    if ($test === 'anyof' && $success) {
                return true;
            }
            if ($test === 'allof' && !$success) {
                return false;
            }

        }

                                                return $test === 'allof';

    }

    
    protected function validateTextMatches(array $texts, array $filters, $test) {

        foreach ($filters as $filter) {

            $success = false;
            foreach ($texts as $haystack) {
                $success = DAV\StringUtil::textMatch($haystack, $filter['value'], $filter['collation'], $filter['match-type']);

                                if ($success) break;
            }
            if ($filter['negate-condition']) {
                $success = !$success;
            }

            if ($success && $test === 'anyof')
                return true;

            if (!$success && $test == 'allof')
                return false;


        }

                                                return $test === 'allof';

    }

    
    function propFindLate(DAV\PropFind $propFind, DAV\INode $node) {

                                        if (strpos($this->server->httpRequest->getHeader('User-Agent'), 'Thunderbird') === false) {
            return;
        }
        $contentType = $propFind->get('{DAV:}getcontenttype');
        list($part) = explode(';', $contentType);
        if ($part === 'text/x-vcard' || $part === 'text/vcard') {
            $propFind->set('{DAV:}getcontenttype', 'text/x-vcard');
        }

    }

    
    function htmlActionsPanel(DAV\INode $node, &$output) {

        if (!$node instanceof AddressBookHome)
            return;

        $output .= '<tr><td colspan="2"><form method="post" action="">
            <h3>Create new address book</h3>
            <input type="hidden" name="sabreAction" value="mkcol" />
            <input type="hidden" name="resourceType" value="{DAV:}collection,{' . self::NS_CARDDAV . '}addressbook" />
            <label>Name (uri):</label> <input type="text" name="name" /><br />
            <label>Display name:</label> <input type="text" name="{DAV:}displayname" /><br />
            <input type="submit" value="create" />
            </form>
            </td></tr>';

        return false;

    }

    
    function httpAfterGet(RequestInterface $request, ResponseInterface $response) {

        if (strpos($response->getHeader('Content-Type'), 'text/vcard') === false) {
            return;
        }

        $target = $this->negotiateVCard($request->getHeader('Accept'), $mimeType);

        $newBody = $this->convertVCard(
            $response->getBody(),
            $target
        );

        $response->setBody($newBody);
        $response->setHeader('Content-Type', $mimeType . '; charset=utf-8');
        $response->setHeader('Content-Length', strlen($newBody));

    }

    
    protected function negotiateVCard($input, &$mimeType = null) {

        $result = HTTP\Util::negotiate(
            $input,
            [
                                'text/x-vcard',
                                                'text/vcard',
                                'text/vcard; version=4.0',
                                'text/vcard; version=3.0',
                                'application/vcard+json',
            ]
        );

        $mimeType = $result;
        switch ($result) {

            default :
            case 'text/x-vcard' :
            case 'text/vcard' :
            case 'text/vcard; version=3.0' :
                $mimeType = 'text/vcard';
                return 'vcard3';
            case 'text/vcard; version=4.0' :
                return 'vcard4';
            case 'application/vcard+json' :
                return 'jcard';

                }
        
    }

    
    protected function convertVCard($data, $target, array $propertiesFilter = null) {

        if (is_resource($data)) {
            $data = stream_get_contents($data);
        }
        $input = VObject\Reader::read($data);
        if (!empty($propertiesFilter)) {
            $propertiesFilter = array_merge(['UID', 'VERSION', 'FN'], $propertiesFilter);
            $keys = array_unique(array_map(function($child) {
                return $child->name;
            }, $input->children()));
            $keys = array_diff($keys, $propertiesFilter);
            foreach ($keys as $key) {
                unset($input->$key);
            }
            $data = $input->serialize();
        }
        $output = null;
        try {

            switch ($target) {
                default :
                case 'vcard3' :
                    if ($input->getDocumentType() === VObject\Document::VCARD30) {
                                                return $data;
                    }
                    $output = $input->convert(VObject\Document::VCARD30);
                    return $output->serialize();
                case 'vcard4' :
                    if ($input->getDocumentType() === VObject\Document::VCARD40) {
                                                return $data;
                    }
                    $output = $input->convert(VObject\Document::VCARD40);
                    return $output->serialize();
                case 'jcard' :
                    $output = $input->convert(VObject\Document::VCARD40);
                    return json_encode($output);

            }

        } finally {

                        $input->destroy();
            if (!is_null($output)) {
                $output->destroy();
            }
        }

    }

    
    function getPluginName() {

        return 'carddav';

    }

    
    function getPluginInfo() {

        return [
            'name'        => $this->getPluginName(),
            'description' => 'Adds support for CardDAV (rfc6352)',
            'link'        => 'http://sabre.io/dav/carddav/',
        ];

    }

}
