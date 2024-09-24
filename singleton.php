<?php
    $aBind = ['start_date' => '2024-09-02 00:00:00', 'end_date' => '2024-09-08 23:59:59'];
        $aMonths = [
            1 => 'Январь', 2 => 'Февраль', 3 => 'Март', 4 => 'Апрель', 5 => 'Май', 6 => 'Июнь',
            7 => 'Июль', 8 => 'Август',  9 => 'Сентябрь', //10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь'
        ];
        $aData ['Сколько документов было создано?'] = 0 ; 
        $aData ['Сколько документов было получено?'] = 0;
        $aData ['Сколько компаний создавали документы?']=0;    
        $aData ['Сколько компаний получали документы?']=0;

        $aQueryForUnion = [];
        $aServices = \app\services\Config::getInstance()->getServices();
        $aDoctypes = [];
        $aDoctypesByMonth = [];
        foreach (\app\services\Config::getInstance()->getDoctypes() as $sKey => $aDtVal) {
            if (isset($aServices[$aDtVal['service_id']])) {
                $aQueryForUnion[] = sprintf(
                    'SELECT id, company_id, created_at::timestamp, document_type, creator FROM %s WHERE is_deleted = 0 AND created_at >= :start_date AND created_at <= :end_date AND NOT(creator_company_identity_code = :dg_global_bin AND description = :description)',
                    $aDtVal['table']
                );
                $aDoctypes[$sKey] = [
                    'title' => $aDtVal['title'],
                    'count' => 0,
                ];
                foreach ($aMonths as $iKey => $sMonths) {
                    $aDoctypesByMonth[$sKey]['title'] = $aDtVal['title'];
                    $aDoctypesByMonth[$sKey]['owner'] = $aServices[$aDtVal['service_id']]['provider'] ?? '';
                    $aDoctypesByMonth[$sKey][$iKey] = 0;
                }
            }
        }
        $aDocuments = $this->oDb
            ->add(implode(' UNION ALL ', $aQueryForUnion))
            ->execute(array_merge($aBind, ['dg_global_bin' => '190740900207', 'description' => 'Добро пожаловать']))
            ->fetchAll();
        $aMarketDocuments = DriverFactory::create(config('app_market_sql_uri'))
            ->add('SELECT id, account_identity_code, created_at::timestamp, document_type, creator, hash,  \'[]\' AS sender_correspondent, \'[]\' AS recipient, \'market\' AS db_name')
            ->add('FROM documents')
            ->add('WHERE created_at >= :start_date AND created_at <= :end_date')
            ->execute($aBind)
            ->fetchAll();

        $aMarketDoctypes = [
            //----------------------------------Market Gov---------------------------------------------------------------------
            'svc_gbd_company_info' => ['title' => 'Справка о регистрации (перерегистрации) юридических лиц', 'owner' => 'Государственные органы РК'],
            'gbd-declaration' => ['title' => 'Декларацию об активах и обязательствах физического лица', 'owner' => 'Государственные органы РК'],
            'svc_video_con' => ['title' => 'Видео ЦОН', 'owner' => 'Государственные органы РК'],
            'gbg-change-legal-location' => ['title' => 'Изменении места нахождения юридического лица, относящегося к субъекту частного предпринимательства, филиала', 'owner' => 'Государственные органы РК'],

            //----------------------------------Market DG----------------------------------------------------------------------
            'svc_offer' => ['title' => 'Отправка оферты на SMS-подписание', 'owner' => ''],
            'svc_signed_file_only' => ['title' => 'Документ', 'owner' => ''],
            'rental-agreement' => ['title' => 'Типовой договор аренды жилого помещения', 'owner' => ''],
            'act-acceptance-transfer' => ['title' => 'Акт приема-передачи жилого помещения в аренду', 'owner' => ''],

            //----------------------------------Market BCC---------------------------------------------------------------------
            'bcc-loan' => ['title' => 'Заявка на кредитование ИП', 'owner' => 'АО "БАНК ЦЕНТРКРЕДИТ"'],

            'check-counterparty' => ['title' => 'Проверка Контрагента', 'owner' => ''],

            //----------------------------------Market Qazpost-----------------------------------------------------------------
            'qazpost-tracking' => ['title' => 'Сервис трекинга', 'owner' => 'АО "КАЗПОЧТА"'],
            'qazpost-call-courier' => ['title' => 'Сервис вызова курьера', 'owner' => 'АО "КАЗПОЧТА"'],

            //----------------------------------Market PNHZ-----------------------------------------------------------------
            'pnhz-shipment-request' => ['title' => 'Заявка на отгрузку', 'owner' => 'ТОО "ПАВЛОДАРСКИЙ НЕФТЕХИМИЧЕСКИЙ ЗАВОД"'],

            //----------------------------------Market AERC-----------------------------------------------------------------
            'aerc-unified-payment-document' => ['title' => 'Заявка на добавление услуги в ЕПД (Единый Платежный Документ)', 'owner' => 'ТОО "Астана-ЕРЦ"'],
            'aerc-unified-payment-document-contract' => ['title' => 'Договор добавление услуги в ЕПД (Единый Платежный Документ)', 'owner' => 'ТОО "Астана-ЕРЦ"'],
        ];
        foreach ($aMarketDoctypes as $sKey => $aDtVal) {
            $aDoctypes[$sKey] = [
                'title' => $aDtVal['title'],
                'count' => 0,
            ];
            foreach ($aMonths as $iKey => $sMonths) {
                $aDoctypesByMonth[$sKey]['title'] = $aDtVal['title'];
                $aDoctypesByMonth[$sKey]['owner'] = $aDtVal['owner'];
                $aDoctypesByMonth[$sKey][$iKey] = 0;
            }
        }
        $aCompanies = [];
        $aDocuments = array_merge($aDocuments, $aMarketDocuments);
        foreach ($aDocuments as $aRow) {
            if (!isset($aRow['company_id'])) {
                $aRow['company_id'] = $aRow['account_identity_code'];
            }
            $aDoctypes[$aRow['document_type']]['count'] += 1;
            $aRow['creator'] = \app\system\Json::decode($aRow['creator']);
            $oDate = new \app\system\Date(strtotime($aRow['created_at']));
            $iKey = $oDate->format('n');
            $aDoctypesByMonth[$aRow['document_type']][$iKey] += 1;
            if (count($aRow['creator'])) {
                $aData['Сколько документов было создано?'] += 1;
                if (!isset($aCompanies['Сколько компаний создавали документы?'][$iKey][$aRow['company_id']])) {
                    $aCompanies['Сколько компаний создавали документы?'][$iKey][$aRow['company_id']] = $aRow['company_id'];
                    $aData['Сколько компаний создавали документы?'] += 1;
                }
            } else {
                $aData['Сколько документов было получено?'] += 1;
                if (!isset($aCompanies['Сколько компаний получали документы?'][$iKey][$aRow['company_id']])) {
                    $aCompanies['Сколько компаний получали документы?'][$iKey][$aRow['company_id']] = $aRow['company_id'];
                    $aData['Сколько компаний получали документы?'] += 1;
                }
            }
        }

        $aDataTitle = ['title' => 'Тип документа', 'owner' => 'Провайдер'] + $aMonths;

        dd($aDoctypesByMonth, count($aDoctypes), $aData);
?>
