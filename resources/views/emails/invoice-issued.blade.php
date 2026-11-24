<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <title>Facture {{ $invoice->invoice_number }}</title>
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #eef2f7;
            font-family: 'Instrument Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
            color: #0f172a;
            line-height: 1.6;
            -webkit-text-size-adjust: 100%;
        }

        table {
            border-collapse: collapse;
        }

        a {
            color: #2563eb;
        }

        .email-shell {
            width: 100%;
            max-width: 640px;
            background-color: #ffffff;
            border: 1px solid #dfe6ef;
            border-radius: 16px;
            overflow: hidden;
        }

        .content-cell {
            padding-left: 36px;
            padding-right: 36px;
        }

        .summary-cell {
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
            color: #64748b;
        }

        .summary-value {
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
            color: #0f172a;
            text-align: right;
            font-weight: 600;
        }

        .summary-cell-last,
        .summary-value-last {
            border-bottom: 0;
        }

        .reference {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', monospace;
            font-size: 14px;
            letter-spacing: 0.02em;
            word-break: break-all;
        }

        @media only screen and (max-width: 640px) {
            .email-shell {
                border-radius: 0 !important;
            }

            .content-cell {
                padding-left: 22px !important;
                padding-right: 22px !important;
            }

            .header-cell {
                padding: 24px 22px !important;
            }

            .amount {
                font-size: 26px !important;
            }
        }
    </style>
</head>

<body>
    <div style="display: none; max-height: 0; overflow: hidden; opacity: 0;">
        Facture {{ $invoice->invoice_number }} — {{ number_format($invoice->total_cents / 100, 2, '.', ' ') }} {{ $invoice->currency->value }} — échéance le {{ $invoice->due_date?->format('d.m.Y') }}
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #eef2f7;">
        <tr>
            <td align="center" style="padding: 36px 16px;">
                <table role="presentation" class="email-shell" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td style="height: 5px; background-color: #2563eb; font-size: 0; line-height: 0;">&nbsp;</td>
                    </tr>

                    <tr>
                        <td class="header-cell" style="padding: 28px 36px 12px 36px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td width="58" valign="middle">
                                        <img
                                            src="{{ $message->embed(public_path('images/icon-512x512.png')) }}"
                                            width="52"
                                            height="52"
                                            alt="{{ $invoice->creditor_name }}"
                                            style="display: block; width: 52px; height: 52px; border-radius: 12px;"
                                        >
                                    </td>
                                    <td valign="middle" style="padding-left: 14px;">
                                        <div style="font-size: 16px; font-weight: 700; color: #0f172a; line-height: 1.3;">
                                            {{ $invoice->creditor_name }}
                                        </div>
                                        <div style="font-size: 12px; font-weight: 500; color: #64748b; letter-spacing: 0.08em; text-transform: uppercase; margin-top: 3px;">
                                            Facturation
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td class="content-cell" style="padding-top: 20px; padding-bottom: 8px;">
                            <div style="font-size: 12px; font-weight: 700; color: #2563eb; letter-spacing: 0.12em; text-transform: uppercase;">
                                Facture
                            </div>
                            <div style="font-size: 30px; font-weight: 700; color: #0f172a; letter-spacing: -0.025em; margin-top: 4px;">
                                {{ $invoice->invoice_number }}
                            </div>

                            <p style="margin: 24px 0 12px; font-size: 15px; color: #0f172a;">
                                Bonjour {{ $invoice->recipient_name }},
                            </p>
                            <p style="margin: 0 0 24px; font-size: 15px; color: #334155;">
                                Veuillez trouver ci-joint votre facture. Le PDF contient le détail des prestations, les
                                modalités de paiement et le QR-bill officiel.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td class="content-cell" style="padding-bottom: 8px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px;">
                                <tr>
                                    <td style="padding: 22px 24px 8px 24px;">
                                        <div style="font-size: 12px; font-weight: 600; color: #64748b; letter-spacing: 0.06em; text-transform: uppercase;">
                                            Montant total
                                        </div>
                                        <div class="amount" style="font-size: 30px; font-weight: 700; color: #0f172a; letter-spacing: -0.025em; margin-top: 4px;">
                                            {{ number_format($invoice->total_cents / 100, 2, '.', ' ') }} {{ $invoice->currency->value }}
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 0 24px 20px 24px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td class="summary-cell">Échéance</td>
                                                <td class="summary-value">{{ $invoice->due_date?->format('d.m.Y') }}</td>
                                            </tr>
                                            <tr>
                                                <td class="summary-cell summary-cell-last">Référence de paiement</td>
                                                <td class="summary-value summary-value-last">
                                                    <span class="reference">{{ $invoice->payment_reference }}</span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td class="content-cell" style="padding-top: 12px; padding-bottom: 8px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-left: 3px solid #2563eb; background-color: #f8fafc;">
                                <tr>
                                    <td style="padding: 14px 16px; font-size: 14px; color: #334155;">
                                        <strong style="color: #0f172a;">Document joint :</strong> facture PDF avec QR-bill.
                                        Conservez la référence de paiement pour tout versement.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td class="content-cell" style="padding-top: 16px; padding-bottom: 30px;">
                            <p style="margin: 0 0 20px; font-size: 15px; color: #334155;">
                                Pour toute question concernant cette facture, contactez le comité.
                            </p>
                            <p style="margin: 0; font-size: 15px; color: #334155;">
                                Cordialement,<br>
                                <strong style="color: #0f172a;">{{ $invoice->creditor_name }}</strong>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td class="content-cell" style="padding-top: 22px; padding-bottom: 24px; background-color: #f8fafc; border-top: 1px solid #e2e8f0;">
                            <div style="font-size: 12px; line-height: 1.65; color: #64748b;">
                                <strong style="color: #334155;">{{ $invoice->creditor_name }}</strong><br>
                                {{ $invoice->creditor_address }}<br>
                                {{ $invoice->creditor_postal_code }} {{ $invoice->creditor_city }} · {{ $invoice->creditor_country }}
                            </div>
                            <div style="font-size: 11px; line-height: 1.6; color: #94a3b8; margin-top: 14px;">
                                Cet email a été envoyé automatiquement. Merci de ne pas y répondre directement.
                            </div>
                        </td>
                    </tr>
                </table>

                <div style="max-width: 640px; margin-top: 16px; font-size: 11px; color: #94a3b8; text-align: center;">
                    Facture {{ $invoice->invoice_number }} · {{ $invoice->creditor_name }}
                </div>
            </td>
        </tr>
    </table>
</body>

</html>
