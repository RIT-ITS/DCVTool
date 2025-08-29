Dynamically Controlled Ventillation Tool - DCVTool
v 0.9

# Introduction

The DCVTool (Dynamically Controlled Ventilation) is a sophisticated data integration and building automation platform designed to optimize HVAC (Heating, Ventilation, and Air Conditioning) systems in an academic environment. It operates by ingesting real-time class and event schedules from an external Student Information System (SIS) and combining this data with a detailed internal model of campus buildings, rooms, and their specific ventilation requirements based on ASHRAE standards. The tool then calculates the precise ventilation needed for each zone based on scheduled occupancy and dynamically generates hourly setpoints. These setpoints are written to a building automation system (like WebCtrl), allowing for significant energy savings by ensuring rooms are only ventilated when occupied, rather than on a fixed, 24/7 schedule. Managed through a web-based administrative interface and automated via scheduled cron jobs, the DCVTool serves as a critical bridge between academic scheduling and physical infrastructure management.


# DCVTool Technical Data Flow Overview

The DCVTool operates as an automated data processing pipeline, transforming raw academic scheduling data into actionable HVAC control commands. The entire process is orchestrated through a series of services and controllers, typically initiated by scheduled cron jobs.

1) Trigger and Authentication: The process begins when a Kubernetes CronJob sends a secure, token-based POST request to the sync.php endpoint. The CronJobController receives this request and uses the TokenValidator service to authenticate the job, ensuring that only authorized processes can initiate a data sync.

2) Data Ingestion and Staging: Based on the validated token, the controller dispatches the task to the AcademicScheduleService. This service connects to an external Student Information System (SIS) via its SOLR search interface. It performs an efficient two-step fetch: first, it retrieves all class meeting patterns for a specific
building and semester, filtering out any classes that lack defined meeting times. Second, it uses the IDs from the valid classes to perform a targeted query for detailed class information like enrollment numbers and course titles. This combined, clean data is then saved into the local class_schedule_data table, which serves as a staging area.

3) Schedule Expansion: The next stage is handled by the TransferService. Its convertSemesterScheduleToDaily method reads the staged class data. For each class, it generates a series of individual daily event records for the entire semester. For example, a single "Mon/Wed" class record is expanded into dozens of distinct event records, one for
each Monday and Wednesday it meets. This "flattened" calendar of events is stored in the expanded_schedule_data table. The service intelligently uses a progress tracking table to avoid reprocessing events that have already been expanded, making subsequent runs highly efficient.

4) Setpoint Calculation and Transfer: The final and most critical step is also performed by the TransferService via its transferToSetpoint method. This function queries the expanded_schedule_data table for events occurring in a near-future window (e.g., the next 24 hours). For each event, it performs the core DCV calculation: it combines the event's student enrollment (enrl_tot), the room's configured uncertainty factor (uncert_amt), and the per-person outdoor air rate (ppl_oa_rate) from ASHRAE standards to determine the precise required airflow (Process Value, or pv). It then generates two records for each event—one to set the calculated airflow at the start time and another to set the airflow to zero at the end time. These final command records are written to the setpoint_write table in a separate WebCtrl database, which is consumed by the physical building automation system to control the HVAC equipment.


# DCVTool Administration Interface: An Overview

The DCVTool's administration interface is a secure, role-based web portal designed for the comprehensive management of all foundational data required for its automated ventilation calculations. It provides authorized administrators with the tools to define, edit, and link the physical, academic, and engineering parameters that drive the system. The
interface is built around a consistent and intuitive user experience, ensuring that complex relationships between different data types can be managed efficiently.

## Common User Experience

Across the various administrative pages, a standardized workflow allows for ease of use and reduces the learning curve:

- Data Display: Records are presented in interactive tables that support sorting, searching, and pagination, making it easy to locate specific entries even in large datasets.
- Creating and Editing: A consistent modal-based system is used for data entry. Clicking an "Add New" button opens a clean form for creating a new record, while clicking an "Edit" button on an existing row populates the same modal with that record's data. This unified approach simplifies the process of both adding and modifying information.
- Secure Deletion: To prevent accidental data loss, deleting a record is a two-step process that requires explicit confirmation from the user through a confirmation dialog.
- Role-Based Access Control: All administrative functions are protected by a granular permissions system. An administrator's ability to view or modify data in a specific section is determined by their assigned role and the permissions configured in the central Settings page.

## Key Administrative Sections

The administrative interface is logically divided into several key areas, each managing a critical component of the DCVTool's operational data.

1. Core Configuration & Settings This is the central control panel for the entire application. On the Settings page, top-level administrators can:
- Manage user permissions by enabling or disabling editing capabilities for every other section of the tool (e.g., "Enable Rooms Editing," "Enable Users Editing").
- Set system-wide operational parameters, such as the default campus for data synchronization, the server addresses for the Student Information System (SIS), and the criteria for the International Energy Conservation Code (IECC).
- Enable or disable features like bulk data importing and detailed system logging.

2. Building & Space Management This area allows for the creation of a detailed digital twin of the campus infrastructure:
Campuses, Buildings, Floors, and Rooms: Admins can manage the physical hierarchy of the campus, from defining different campuses (e.g., Main, Dubai) to adding buildings, specifying their floors, and detailing individual rooms.

Room Details: For each room, administrators input critical data points like its area, official population capacity, and its assigned ASHRAE occupancy category, which are fundamental inputs for the ventilation calculations.

3. HVAC & Standards Management This is where the engineering logic of the system is defined:
ASHRAE & NCES Standards: Admins manage the ASHRAE 62.1 occupancy categories, defining the required outdoor air rates per person and per area. These are linked to official NCES space use codes, ensuring that a "Classroom" is treated differently from a "Laboratory" according to industry standards.

Uncertainty Factors: To provide a ventilation safety buffer, admins can create "Uncertainty" values—fixed numbers of additional occupants—and associate them with specific ASHRAE categories.

AHUs and Zones: Air Handling Units (AHUs) and the HVAC zones they serve are defined here. This establishes the logical HVAC structure of a building.

Space/Zone Cross-Reference: This section allows admins to map a physical room to one or more HVAC zones. For rooms served by multiple VAV boxes, admins can specify the exact percentage of the room's area and population served by each zone, ensuring precise airflow distribution.

4. Academic & Event Management This section controls the scheduling data that drives occupancy:
Terms: Administrators define academic semesters by setting their name, official term code, and start/end dates. This ensures the cron jobs pull class schedule data for the correct time period.

Manual Event Entry: The interface allows for the manual creation and editing of events in the expanded_schedule_data table. This is useful for adding non-class-related bookings or making manual adjustments to the schedule that will be used for setpoint calculations.

5. System & User Administration:
User Management: Admins can create, edit, and delete user accounts, assign roles (e.g., Administrator, Editor), and manage access to the tool.

Device/Equipment Map: This section provides the final link in the automation chain. Here, the logical HVAC zone codes (e.g., GOL-VAV-101) are mapped to their physical uname paths used by the WebCtrl building automation system, ensuring that calculated setpoints are sent to the correct piece of equipment. Data Imports: For streamlined setup and large-scale updates, the tool includes powerful bulk import functionality, allowing administrators to upload CSV files to populate building, room, class schedule, and zone data.

Reporting/Metrics: While the data being received back from WebCTRL can be imported into any data analyst tool, DCVTool utilizes the open-source Grafana system to view reports and data.

- - -

# DCVTool Technology Stack Overview
The DCVTool is a full-stack web application built with a combination of robust, industry-standard open-source technologies. It is architected as a modern PHP application with a dynamic, data-driven frontend, all designed to be deployed within a containerized environment.

The server-side logic is primarily written in modern, object-oriented PHP (8.x). The application follows a service-oriented architecture without relying on a major framework like Laravel or Symfony, instead using a custom Dependency Injection (DI) Container to manage services and their dependencies.

Because the Core Language is PHP, all database communication is handled through PHP's native PDO (PHP Data Objects) extension, utilizing prepared statements to ensure security against SQL injection. Backend dependencies are managed by Composer. The application is served by an Apache Web Server, which uses the mod_rewrite module and an .htaccess file
to handle URL routing, creating user-friendly API endpoints.

The system communicates with an external Student Information System (SIS) by making HTTP requests to its Apache SOLR search API, using PHP's native cURL library to fetch class and event data.

The administrative interface is a rich, single-page application experience built on a foundation of standard web technologies and powered by the popular admin template AdminLTE. HTML5, CSS3, Javascript (ES6+) are the foundation of this, utilizing Bootstrap 4, JQuery, Datatables.js, and Toastr.js for the functionality of the various pages.

Due to the security considerations and separation of concerns where this application was built, it makes use of two Postqresql databases - one for the storage of the application's data, and the other for the import and export storage of data that is being transferred to and from the WebCTRL Building Automation System.

DCVTool is designed for a modern, automated deployment workflow. The architecture and application is built to be deployed within a Kubernetes cluster via Helm. Automation of the import and export scripts is accomplished via Kubernetes Cronjobs, which securely trigger the various data synchronization and processing scripts via token-based
authentication.


- - -

# Installation and Setup
## Prerequisites
This install assumes the user has the following:
1) A Kubernetes server running and configured.
2) A namespace configured for this application to run in.
3) Helm configured for Kubernetes.
4) A PostgreSQL server running and configured.
5) A PostgreSQL database created on that Postgres Server.
6) Username and password for DCV to use to access the database listed in #4
7) A PostgreSQL database created for the WebCTRL to export data to (it can be used for setpoint_write data as well)
8) Username and password for DCV to use to access the WebCTRL database.
9) The user installing DCVTool needs kubectl (and/or kubie) and helm configured on their local machine to access the Kubernetes Namespace where this will be installed.

## Part A - Pre-installation Configuration within the chosen Kubernetes namespace
A.1) In your Kubernetes namespace, go to the Secrets area and create an Opaque Secret for the SAML Settings. Remember whatever name you give it as you will need that name in #Installation, Step 2. This SAML Settings Secret should contain a key of "settings" with the value being the SAML configuration information you received from your Identity Provider
relating to the SAML login setup. Here is an example:
```
{
  "strict": true,
  "debug": true,
  "baseurl": "https://example.com",
  "sp": {
    "entityId": "https://example.com/saml",
    "assertionConsumerService": {
      "url": "https://example.com/consume.php",
      "binding": "urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST"
    },
    "singleLogoutService": {
      "url": "https://example.com/slo.php",
      "binding": "urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect"
    },
    "NameIDFormat": "urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified"
  },
  "idp": {
    "entityId": "https://shibboleth.example.com/idp/shibboleth",
    "singleSignOnService": {
      "url": "https://shibboleth.example.com/idp/profile/SAML2/Redirect/SSO",
      "binding": "urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect"
    },
    "singleLogoutService": {
      "url": "https://shibboleth.example.com/logout.html",
      "binding": "urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect"
    },
    "x509cert": " Certificate Goes Here "
  }
}
```
A.2) Create a second Opaque Secret in Kubernetes for the database connection information and credentials. It should have the following keys set: DB_HOST, DB_NAME, DB_PASS, DB_USER, with the appropriate database server and credentials in the value fields.

A.3) Create a third Opaque Secret in Kubernetes for the WebCTRL database connection information and credentials. It should  have the following keys set: WCT_DB_ALT_NAME, WCT_DB_HOST, WCT_DB_MGRPASS, WCT_DB_MGRUSER, WCT_DB_NAME, WCT_DB_PASS, WCT_DB_USER, with the appropriate credentials for the WebCTRL Postresql database in the value fields.

A.4) Create a fourth Opaque Secret in Kubernetes for the cronjob connection credentials and it should have the following keys set: CORS_ALLOWED_ORIGIN, SETPOINTSYNC, SISCLASSSYNC, SISCLASSTOEXPANDED, SISEXAMSYNC, TRENDSYNC, with the Cors value entry set to the domain name that DCVTool will be running on, and the rest of the values set to random character
values to use as keys and identifiers for the scripts on the backend to know which cronjob is running.

A.5) Create a fifth Opaque Secret in Kubernetes for the backup cronjob to use to access the database and backup the data. It should contain the keys POSTGRES_PW, POSTGRES_USER with the appropriate username and password to the DCV db.

A.6) Create a sixth Opaque Secret in Kubernetes for the Grafana system to use. It should have the following keys: DB_HOST, DB_PASSWORD, DB_USER, graf-pw, graf-user. (note that the db name is set by the values file).

A.7) You can use the SQL in the DCVDatabase.sql file to create the tables in the database for the DCVTool to use.

## Section B - Installation of DCVTool
B.1) Download the code.
B.2) Rename the `/dcvtool/chart/values-example.yaml` to `values.yaml` and set all values as needed to match your infrastructure. Comments in the file will help you navigate it.
B.3) open a terminal on your local machine.
B.4) CD to the dcvtool directory. `cd /dcvtool`
B.5) Using Kubie or Kubectl log into your kubernetes infrastructure and namespace.
B.6) from the `/dcvtool` directory run:
`helm upgrade --install <your-chosen-application-name> ./chart -n <your-namespace-here>`
B.7) If everything in Kubernetes and within the values.yaml is correctly configured, you should have DCVTool up and shortly!
