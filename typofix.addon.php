<?php
/* Copyright (c) 2018 Xvezda <https://xvezda.com> */

if (!defined('__XE__')) exit();

if ($called_position == 'after_module_proc') {
    if ($this->act == 'IS' && Context::get('is_keyword')
        || $this->act == 'dispBoardContent' && Context::get('search_keyword')) {
        $parameter = '';
        $keyword = '';

        switch ($this->module) {
        case 'board':
            $parameter = 'search_keyword';
            $keyword = Context::get('search_keyword');
            break;
        case 'integration_search':
            $parameter = 'is_keyword';
            $keyword = Context::get('is_keyword');
            break;
        default:
            break;
        }
        if (!$keyword || !$parameter
         || !$addon_info->client_id || !$addon_info->client_secret) return;

        //echo $addon_info->client_id;
        $api_url = 'https://openapi.naver.com/v1/search/errata.xml';
        $api_url .= '?query=' . $keyword;

        $api_header = array();
        $api_header['Host'] = 'openapi.naver.com';
        $api_header['Pragma'] = 'no-cache';
        $api_header['Accept'] = '*/*';
        // Set naver open api requirements
        $api_header['X-Naver-Client-Id'] = $addon_info->client_id;
        $api_header['X-Naver-Client-Secret'] = $addon_info->client_secret;

        $request_config = array();
        $request_config['ssl_verify_peer'] = false;

        $buff = FileHandler::getRemoteResource($api_url, null, 3, 'GET', null,
                $api_header, array(), array(), $request_config);

        $xml = new XmlParser();
        $xmlDoc = $xml->parse($buff);

        if (!$buff) return;

        $result = $xmlDoc->result->item->errata->body;

        if (!$result) return;

        // Redirect to suggestion
        $this->setRedirectUrl(getNotEncodedUrl($parameter, $result, 'typo_keyword', $keyword));
    }
}


/* End of file typofix */
/* Location: ./addons/typofix/typofix.addon.php */
