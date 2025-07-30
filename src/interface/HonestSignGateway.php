<?php
interface HonestSignGateway {
    public function validateMark(MarkingCode $code): HonestSignResult;
}