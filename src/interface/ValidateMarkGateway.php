<?php
interface ValidateMarkGateway {
    public function validateMark(MarkingCode $code): HonestSignResult;
}