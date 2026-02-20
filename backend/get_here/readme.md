INSTRUCTION HERE

paayos na lang po ng mga directory


LEAVE REQUEST PO ITO
// ------------------ Access Control ------------------
if (!isset($_SESSION['profession']) || $_SESSION['profession'] !== 'Doctor') {
    header('Location: login.php');
    exit();
}

PAYSLIP VIEWING PO ITO
// ------------------ Access Control ------------------
if (!isset($_SESSION['profession']) || $_SESSION['profession'] !== 'Doctor') {
    header('Location: login.php');
    exit();
}

papalit na lang po yung Doctor based po sa panel nyo.
ang ilalagay is:
Phramacist (if sa employee side ng Pharmacist)
Nurse (if sa employee side ng Nurse)
Doctor (if sa employee side ng Doctor)
Laboratorist (if sa employee side ng Laboratorist)
Accountant (if sa employee side ng Accountant)


ETO PO YUNG NAVIGATION LINK SA SIDE BAR
            <li class="sidebar-item">
                <a href="leave_request.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-walking" viewBox="0 0 16 16">
                        <path d="M9.5 1.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0M6.44 3.752A.75.75 0 0 1 7 3.5h1.445c.742 0 1.32.643 1.243 1.38l-.43 4.083a1.8 1.8 0 0 1-.088.395l-.318.906.213.242a.8.8 0 0 1 .114.175l2 4.25a.75.75 0 1 1-1.357.638l-1.956-4.154-1.68-1.921A.75.75 0 0 1 6 8.96l.138-2.613-.435.489-.464 2.786a.75.75 0 1 1-1.48-.246l.5-3a.75.75 0 0 1 .18-.375l2-2.25Z"/>
                        <path d="M6.25 11.745v-1.418l1.204 1.375.261.524a.8.8 0 0 1-.12.231l-2.5 3.25a.75.75 0 1 1-1.19-.914zm4.22-4.215-.494-.494.205-1.843.006-.067 1.124 1.124h1.44a.75.75 0 0 1 0 1.5H11a.75.75 0 0 1-.531-.22Z"/>
                    </svg>
                    <span style="font-size: 18px;">Leave Request</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="payslip_viewing.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-earmark-text-fill" viewBox="0 0 16 16">
                        <path d="M9.293 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.707A1 1 0 0 0 13.707 4L10 .293A1 1 0 0 0 9.293 0M9.5 3.5v-2l3 3h-2a1 1 0 0 1-1-1M4.5 9a.5.5 0 0 1 0-1h7a.5.5 0 0 1 0 1zM4 10.5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5m.5 2.5a.5.5 0 0 1 0-1h4a.5.5 0 0 1 0 1z"/>
                    </svg>
                    <span style="font-size: 18px;">Payslip Viewing</span>
                </a>
            </li>


YUNG REPLACEMENT BUTTON KUNIN NYO NA LANG YUNG MGA BUTTON NYO DUN MAY MGA NAKA LAGAY NA DUN KUNG KANINO PO IYON
ILALAGAY DAW PO PALA ITO SA BABA NG SIDE BAR

YUNG SA SUBMIT REPLACEMENT REQUEST LAHAT PO KAYO MERON NYAN


REMINDER LANG PO!!!

ADMIN SIDE
replacement_button.php
submit_replacement_request.php

EMPLOYEE SIDE
leave_request.css
leave_request.php
payslip_viewing.css
payslip_viewing.php
view_payslip.php


KAPAG MAY TANONG PO MAG ASK NA LANG PO SAKIN :))
MAGSABI DIN PO KAPAG NAG ERROR PARA MAAYOS KO PO



sa repair_request.php 

add nyo na lang for maintenance ng mga equipment nyo lagay nyo na lang ng sidebar nyo goods na siya 