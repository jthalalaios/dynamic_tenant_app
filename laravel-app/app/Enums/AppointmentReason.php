<?php

namespace App\Enums;

enum AppointmentReason: string
{
    case Consultation = 'consultation';
    case FollowUp = 'follow_up';
    case NewPatient = 'new_patient';
    case Exam = 'exam';
}
