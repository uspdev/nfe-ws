<?php

//require_once('../lib/nfephp/libs/External/FPDF/fpdf.php');


class sPDF extends TCPDF
{
    private $drawColor = 150;
    private $fillColor = 240;
    private $linewidth = 0.3;
    private $font = 'dejavusans';
    private $fontsize = 8;
    private $botton = -10;
    public $system = 'Default system var';
    public $script = 'Default script var';

    function myHeader()
    {
        $this->SetFont($this->font, '', 14);
        $this->Ln(1);
        $this->Cell(100, 12, 'Consulta Resumida', 'B', 0, 'L', false);
        $this->Cell(80, 12, 'NF-E', 'B', 0, 'R', false);
    }

    function myFooter()
    {
        $this->SetAutoPageBreak(true, 0);
        $this->SetY(-15);
        $this->SetLineWidth($this->linewidth);
        $this->SetDrawColor(0, 0, 0);
        $this->SetFont($this->font, '', $this->fontsize * 4 / 5);
        $this->Cell(0, 7, utf8_decode($this->system . ' - ' . $this->script), 'T');
    }

    function h2($text)
    {
        $this->SetFont($this->font, 'B', $this->fontsize);
        $this->SetTextColor(255, 0, 0);
        $this->SetDrawColor($this->drawColor);
        $this->SetFillColor($this->fillColor);
        $this->Cell(0, 6, utf8_decode($text), 'TB', 0, '', true);
        $this->Ln();
    }

    function ImprovedTable($w, $header, $data, $lines = 'single')
    {
        $this->SetLineWidth($this->linewidth);
        $this->SetFont($this->font, 'B', $this->fontsize - 1);
        $this->SetTextColor(0, 0, 0);
        $fill = false;

        // Header
        $this->Ln(0.5);
        for ($i = 0; $i < count($header); $i++)
            $this->Cell($w[$i], 6, utf8_decode($header[$i]), 0, 0, 'L', $fill);
        $this->Ln();

        // Data
        $this->SetFont($this->font, '', $this->fontsize);
        $this->SetFillColor($this->fillColor);
        $fill = true;
        if ($lines == 'multi')
            foreach ($data as $row) {
                $i = 0;
                foreach ($row as $val) {
                    $this->Cell($w[$i] - 0.8, 6, $val, 0, 0, 'L', $fill);
                    $this->SetX($this->GetX() + 0.8);
                    $i++;
                }
                $this->Ln();
                $this->Ln(0.8);
            }
        else {
            $i = 0;
            foreach ($data as $val) {
                $this->Cell($w[$i] - 0.8, 6, $val, 0, 0, 'L', $fill);
                $this->SetX($this->GetX() + 0.8);
                $i++;
            }
            $this->Ln();
        }
    }
}


?>