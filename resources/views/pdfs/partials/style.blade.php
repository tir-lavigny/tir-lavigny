@php
    $instrumentSans = storage_path('fonts/Instrument_Sans/InstrumentSans-VariableFont_wdth,wght.ttf');
@endphp
<style>
    @font-face {
        font-family: "Instrument Sans";
        font-style: normal;
        font-weight: 400;
        src: url("file://{{ $instrumentSans }}") format("truetype");
    }
    @font-face {
        font-family: "Instrument Sans";
        font-style: normal;
        font-weight: 600;
        src: url("file://{{ $instrumentSans }}") format("truetype");
    }
    @font-face {
        font-family: "Instrument Sans";
        font-style: normal;
        font-weight: 700;
        src: url("file://{{ $instrumentSans }}") format("truetype");
    }

    @page { margin: 16mm 18mm 16mm 18mm; }

    body {
        font-family: "Instrument Sans", sans-serif;
        color: #020817;
        font-size: 9.5pt;
        line-height: 1.45;
    }

    h1 {
        font-size: 23pt;
        line-height: 1.05;
        font-weight: 600;
        color: #2563eb;
        letter-spacing: -0.03em;
        margin: 0;
    }

    h2 {
        font-size: 9pt;
        line-height: 1.2;
        font-weight: 600;
        color: #2563eb;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        border-bottom: 0.35mm solid #e2e8f0;
        padding-bottom: 1.5mm;
        margin: 8mm 0 3mm;
    }

    .muted { color: #64748b; }
    .small { font-size: 8pt; }
    .accent { color: #2563eb; }
    .right { text-align: right; }
    .left { text-align: left; }

    .page-top {
        position: relative;
        height: 70mm;
    }

    .brand-right,
    .brand-left {
        position: absolute;
        top: 0;
        width: 100mm;
        border-collapse: collapse;
    }
    .brand-right { right: 0; }
    .brand-left { left: 0; }
    .brand-right td,
    .brand-left td { vertical-align: middle; }
    .brand-logo { width: 17mm; }
    .brand-right .brand-logo { text-align: right; }
    .brand-left .brand-logo { text-align: left; }
    .brand-logo img { width: 15mm; height: 15mm; }
    .brand-name { font-size: 11pt; font-weight: 600; color: #020817; }
    .brand-meta { color: #64748b; font-size: 7.5pt; line-height: 1.35; }

    .address-window {
        position: absolute;
        top: 29mm;
        width: 85mm;
        min-height: 40mm;
        font-size: 10pt;
        line-height: 1.5;
    }
    .address-window.window-left { left: 2mm; }
    .address-window.window-right { right: -6mm; }

    .doc-summary {
        margin: 0 0 1mm;
        padding-bottom: 2mm;
        border-bottom: 0.25mm solid #e2e8f0;
    }
    .doc-summary .type {
        color: #64748b;
        font-size: 8pt;
        font-weight: 600;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        margin-right: 1.5mm;
    }
    .doc-summary .ref {
        color: #2563eb;
        font-size: 10pt;
        font-weight: 600;
    }
    .doc-summary .meta {
        color: #64748b;
        font-size: 8pt;
        margin-top: 0.8mm;
    }

    .eyebrow {
        color: #64748b;
        font-size: 7.5pt;
        font-weight: 600;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .party { font-size: 9.5pt; line-height: 1.5; }
    .party strong { font-weight: 600; }

    .lines {
        width: 100%;
        border-collapse: collapse;
        margin-top: 4mm;
    }
    .lines th {
        text-align: left;
        color: #64748b;
        border-bottom: 0.4mm solid #2563eb;
        padding: 2mm 1.2mm;
        font-size: 7.5pt;
        font-weight: 600;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }
    .lines td {
        border-bottom: 0.2mm solid #e2e8f0;
        padding: 2.2mm 1.2mm;
        vertical-align: top;
    }
    .lines th.right,
    .lines td.right {
        text-align: right;
    }

    .totals {
        width: 72mm;
        margin-left: auto;
        margin-top: 4mm;
        border-collapse: collapse;
    }
    .totals td { padding: 1.2mm 0; }
    .totals .grand td {
        border-top: 0.5mm solid #2563eb;
        font-weight: 600;
        font-size: 11pt;
        padding-top: 2mm;
    }

    .reference {
        margin-top: 7mm;
        padding: 3.5mm 4mm;
        background: #f8fafc;
        border-left: 1.2mm solid #2563eb;
    }
    .reference .reference-value {
        font-size: 11pt;
        font-weight: 600;
        color: #020817;
        margin-top: 1mm;
    }

    .note {
        margin-top: 7mm;
        padding-top: 3mm;
        border-top: 0.25mm solid #e2e8f0;
        color: #64748b;
        font-size: 8pt;
    }

    .status {
        display: inline-block;
        padding: 1mm 2.5mm;
        background: #eff6ff;
        color: #2563eb;
        font-size: 7.5pt;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }
</style>
