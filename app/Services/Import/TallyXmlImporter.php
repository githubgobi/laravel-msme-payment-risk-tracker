<?php

namespace App\Services\Import;

use App\DTOs\ImportRow;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Parses a Tally ERP XML export into a collection of ImportRow DTOs.
 *
 * Supports both Tally ERP 9 and Tally Prime export formats.
 * Handles UTF-8 and Windows-1252 encoded files.
 *
 * Tally XML structure (simplified):
 *   <ENVELOPE>
 *     <BODY>
 *       <IMPORTDATA>
 *         <REQUESTDATA>
 *           <TALLYMESSAGE>
 *             <VOUCHER VCHTYPE="Purchase">
 *               <DATE>20250115</DATE>
 *               <VOUCHERNUMBER>PO-001</VOUCHERNUMBER>
 *               <PARTYLEDGERNAME>ABC Suppliers</PARTYLEDGERNAME>
 *               <AMOUNT>-500000</AMOUNT>
 *             </VOUCHER>
 *           </TALLYMESSAGE>
 *         </REQUESTDATA>
 *       </IMPORTDATA>
 *     </BODY>
 *   </ENVELOPE>
 *
 * Amounts: Tally uses negative for credits (money owed). We use abs().
 */
final class TallyXmlImporter
{
    private const PURCHASE_VOUCHER_TYPES = ['purchase', 'purchase voucher'];
    private const MAX_FILE_SIZE_BYTES    = 52_428_800; // 50MB

    /**
     * Parse the Tally XML file and return a Collection of ImportRow DTOs.
     *
     * @param  string  $absolutePath  Full filesystem path to the file
     * @return Collection<ImportRow>
     * @throws RuntimeException  on unreadable or malformed XML
     */
    public function parse(string $absolutePath): Collection
    {
        if (! file_exists($absolutePath)) {
            throw new RuntimeException("File not found: {$absolutePath}");
        }

        if (filesize($absolutePath) > self::MAX_FILE_SIZE_BYTES) {
            throw new RuntimeException('Tally XML file exceeds the 50MB limit');
        }

        $content = file_get_contents($absolutePath);

        // Detect and convert encoding if not UTF-8
        $encoding = mb_detect_encoding($content, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }

        // Suppress XML warnings; we validate the result
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        libxml_clear_errors();

        if ($xml === false) {
            throw new RuntimeException('Could not parse XML file. Please ensure it is a valid Tally export.');
        }

        return $this->extractVouchers($xml);
    }

    private function extractVouchers(\SimpleXMLElement $xml): Collection
    {
        $rows      = collect();
        $rowNumber = 1;

        // Navigate through either Tally ERP 9 or Tally Prime structure.
        // Both store vouchers under //VOUCHER nodes.
        $vouchers = $xml->xpath('//VOUCHER') ?: [];

        foreach ($vouchers as $voucher) {
            $vchType = strtolower(trim((string) ($voucher['VCHTYPE'] ?? $voucher->VOUCHERTYPE ?? '')));

            if (! in_array($vchType, self::PURCHASE_VOUCHER_TYPES, true)) {
                continue;
            }

            $invoiceNumber = trim((string) ($voucher->VOUCHERNUMBER ?? $voucher->BILLDATE ?? ''));
            $invoiceDate   = trim((string) ($voucher->DATE ?? ''));
            $vendorName    = trim((string) ($voucher->PARTYLEDGERNAME ?? ''));

            // Amount: Tally may store negative (credit side). Use abs().
            $rawAmount = trim((string) ($voucher->AMOUNT ?? '0'));
            $amount    = (string) abs((float) str_replace(',', '', $rawAmount));

            $gstin     = trim((string) ($voucher->GSTIN ?? $voucher->PARTYGSTREGNO ?? ''));
            $narration = trim((string) ($voucher->NARRATION ?? ''));

            $rows->push(new ImportRow(
                rowNumber:       $rowNumber,
                invoiceNumber:   $invoiceNumber,
                invoiceDate:     $invoiceDate,
                vendorName:      $vendorName,
                amount:          $amount,
                gstin:           $gstin,
                udyamNumber:     '',
                paidAmount:      '0',
                agreementExists: 'false',
                narration:       $narration,
            ));

            $rowNumber++;
        }

        return $rows;
    }
}
