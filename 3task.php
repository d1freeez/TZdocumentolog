$aBind = ['start_date' => '2024-01-01 00:00:00', 'end_date' => '2024-09-24 23:59:59'];
        $aData = [];
        $aNewCompanies = $this->oDb->add('SELECT id, created_at::timestamp, identity_code AS bin_iin, is_verified, ')
            ->add('CASE WHEN 
short_name <> \'\' AND type <> \'\' 
THEN CONCAT(type, \' "\', short_name, \'"\') 
ELSE CASE WHEN short_name <> \'\' THEN short_name ELSE full_name END
END AS name')
            ->add('FROM companies')
            ->add('WHERE is_deleted = 0 AND is_not_registered = 0 AND type != \'ФЛ\'')
            ->add('AND created_at >= :start_date AND created_at <= :end_date')
            ->add('ORDER BY id')
            ->execute($aBind)->fetchAll();
        $aCompanyIds = [];
        $aCompanyIdentityCodes = [];
        $aMappingCompanyBinIin = [];
        foreach ($aNewCompanies as $aRow) {
            $aCompanyIds[] = $aRow['id'];
            $aCompanyIdentityCodes[] = $aRow['bin_iin'];
            $aMappingCompanyBinIin[$aRow['bin_iin']] = $aRow['id'];
            $aData[$aRow['id']] = [
                'new_company_id' => $aRow['id'],
                'new_company_created_at' => $aRow['created_at'],
                'new_company_name' => $aRow['name'],
                'new_company_bin_iin' => $aRow['bin_iin'],
                'doc_correspondent' => '',
                'doc_hash' => '',
                'doc_created_at' => '',
                'doc_type' => '',
                'doc_status' => '',
                'doc_is_service' => ''
            ];
        }
        $aBusinessDoctypes = [];
        $aBusinessServices = \app\services\Config::getInstance()->getServices();
        foreach (\app\services\Config::getInstance()->getDoctypes() as $sDtKey => $aDtVal) {
            $aBusinessDoctypes[$sDtKey] = [
                'title' => $aDtVal['title'],
                'is_service' => isset($aBusinessServices[$aDtVal['service_id']]),
                'owner' => $aBusinessServices[$aDtVal['service_id']]['provider'] ?? ''
            ];
        }
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
        $aMarketDocuments = DriverFactory::create(config('app_market_sql_uri'))
            ->add('SELECT id, account_identity_code, created_at::timestamp, document_type, creator, hash,  \'[]\' AS sender_correspondent, \'[]\' AS recipient, \'market\' AS db_name')
            ->add('FROM documents')
            ->add('WHERE account_identity_code = ANY(:account_identity_codes) AND created_at >= :start_date AND created_at <= :end_date')
            ->execute(array_merge($aBind, ['account_identity_codes' => \app\system\db\Util::postgreSerialize($aCompanyIdentityCodes)]))
            ->fetchAll();
        $aBusinessDocuments = $this->oDb
            ->add('SELECT id, company_id, created_at::timestamp, document_type, creator, hash, sender_correspondent, recipient, \'business\' AS db_name')
            ->add('FROM documents')
            ->add('WHERE company_id = ANY(:company_ids) AND created_at >= :start_date AND created_at <= :end_date')
            ->add('AND NOT(creator_company_identity_code = :dg_global_bin AND description = :description)')
            ->execute(array_merge($aBind, [
                'company_ids' => \app\system\db\Util::postgreSerialize($aCompanyIds),
                'dg_global_bin' => '190740900207',
                'description' => 'Добро пожаловать',
            ]))
            ->fetchAll();
        $aDocuments = array_merge($aMarketDocuments, $aBusinessDocuments);
        usort($aDocuments, function ($aDoc1, $aDoc2) {
            return $aDoc1['created_at'] <=> $aDoc2['created_at'];
        });
        foreach ($aDocuments as $aRow) {
            if ($aRow['db_name'] === 'market') {
                $aRow['company_id'] = $aMappingCompanyBinIin[$aRow['account_identity_code']];
            }
            if (mb_strlen($aData[$aRow['company_id']]['doc_hash'])) {
                continue;
            }
            $aRow['creator'] = \app\system\Json::decode($aRow['creator']);
            $aRow['sender_correspondent'] = \app\system\Json::decode($aRow['sender_correspondent']);
            $aRow['recipient'] = \app\system\Json::decode($aRow['recipient']);

            if ($aRow['db_name'] === 'market') {//услуги в маркете
                $sDoctype = $aMarketDoctypes[$aRow['document_type']]['title'];
                $sDocCor = $aMarketDoctypes[$aRow['document_type']]['owner'];
                $isService = true;
            } else {
                $sDoctype = $aBusinessDoctypes[$aRow['document_type']]['title'];
                if ($aBusinessDoctypes[$aRow['document_type']]['is_service'] === true) {//услуги в бизнесе
                    $sDocCor = $aBusinessDoctypes[$aRow['document_type']]['owner'];
                    $isService = true;
                } else {//стандартные типы
                    $sDocCor = implode(', ', count($aRow['creator']) ? $aRow['recipient'] : $aRow['sender_correspondent']);
                    $isService = false;
                }
            }
            $aData[$aRow['company_id']]['doc_correspondent'] = $sDocCor;
            $aData[$aRow['company_id']]['doc_hash'] = $aRow['hash'];
            $aData[$aRow['company_id']]['doc_created_at'] = $aRow['created_at'];
            $aData[$aRow['company_id']]['doc_type'] = $sDoctype;
            $aData[$aRow['company_id']]['doc_status'] = count($aRow['creator']) ? 'Отправлен' : 'Получен';
            $aData[$aRow['company_id']]['doc_is_service'] = $isService ? 'Да' : 'Нет';
        }
