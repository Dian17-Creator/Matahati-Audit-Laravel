<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public $pdfData;
    public $fileName;
    public $reportName;
    public $messageText;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($pdfData, $fileName, $reportName, $messageText)
    {
        $this->pdfData = $pdfData;
        $this->fileName = $fileName;
        $this->reportName = $reportName;
        $this->messageText = $messageText;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject($this->reportName)
                    ->view('emails.report-mail')
                    ->attachData($this->pdfData, $this->fileName, [
                        'mime' => 'application/pdf',
                    ]);
    }
}
