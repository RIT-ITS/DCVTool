-- public.ahu_data definition

-- Drop table

-- DROP TABLE public.ahu_data;

CREATE TABLE public.ahu_data (
                                 id serial4 NOT NULL,
                                 ahu_name varchar NULL,
                                 ahu_code varchar NULL,
                                 active bool NULL DEFAULT false,
                                 CONSTRAINT ahu_data_pk PRIMARY KEY (id),
                                 CONSTRAINT ahu_hid_active_pk2 UNIQUE (ahu_code, active)
);
CREATE INDEX ahu_data_hid_index ON public.ahu_data USING btree (ahu_code);


-- public.ashrae_6_1 definition

-- Drop table

-- DROP TABLE public.ashrae_6_1;

CREATE TABLE public.ashrae_6_1 (
                                   id serial4 NOT NULL,
                                   ok bool NULL,
                                   ppl_oa_rate numeric NULL,
                                   area_oa_rate numeric NULL,
                                   occ_density numeric NULL,
                                   occ_stdby_allowed bool NULL,
                                   notes text NULL,
                                   category varchar NULL,
                                   "type" int4 NULL,
                                   CONSTRAINT ashrae_6_1_pk PRIMARY KEY (id)
);


-- public.ashrae_6_1_types definition

-- Drop table

-- DROP TABLE public.ashrae_6_1_types;

CREATE TABLE public.ashrae_6_1_types (
                                         id serial4 NOT NULL,
                                         "type" varchar NULL,
                                         CONSTRAINT ashrae_6_1_types_pk PRIMARY KEY (id)
);


-- public.ashrae_6_4 definition

-- Drop table

-- DROP TABLE public.ashrae_6_4;

CREATE TABLE public.ashrae_6_4 (
                                   id serial4 NOT NULL,
                                   cat int4 NULL,
                                   ez numeric NULL,
                                   "configuration" varchar(200) NULL,
                                   CONSTRAINT ashrae_6_4_pk PRIMARY KEY (id)
);


-- public.ashrae_6_4_categories definition

-- Drop table

-- DROP TABLE public.ashrae_6_4_categories;

CREATE TABLE public.ashrae_6_4_categories (
                                              id serial4 NOT NULL,
                                              category_name varchar NULL,
                                              CONSTRAINT ashrae_6_4_categories_pk PRIMARY KEY (id)
);


-- public.buildings definition

-- Drop table

-- DROP TABLE public.buildings;

CREATE TABLE public.buildings (
                                  id serial4 NOT NULL,
                                  bldg_num varchar NOT NULL,
                                  bldg_name varchar NOT NULL,
                                  campus_id int4 NOT NULL,
                                  short_desc varchar NULL,
                                  full_desc text NULL,
                                  facility_code varchar NULL,
                                  active bool NULL DEFAULT false,
                                  CONSTRAINT buildings_pk PRIMARY KEY (id)
);
CREATE INDEX buildings__faccode__index ON public.buildings USING btree (facility_code);
CREATE INDEX buildings_num_index ON public.buildings USING btree (bldg_num);


-- public.campus definition

-- Drop table

-- DROP TABLE public.campus;

CREATE TABLE public.campus (
                               id serial4 NOT NULL,
                               code varchar NOT NULL,
                               campus_name varchar NOT NULL,
                               utc_offset varchar NOT NULL,
                               campus_num varchar NULL,
                               active bool NULL DEFAULT false,
                               CONSTRAINT campus_hid_act_pk2 UNIQUE (campus_num, active),
                               CONSTRAINT campus_pk PRIMARY KEY (id)
);
CREATE UNIQUE INDEX campus_uid_idx ON public.campus USING btree (campus_num);


-- public.class_expanded_progress definition

-- Drop table

-- DROP TABLE public.class_expanded_progress;

CREATE TABLE public.class_expanded_progress (
                                                id bigserial NOT NULL,
                                                pp_search_id varchar NOT NULL,
                                                strm int4 NOT NULL,
                                                last_processed date NOT NULL,
                                                last_updated date NULL DEFAULT now(),
                                                CONSTRAINT class_expanded_progress_pk PRIMARY KEY (id),
                                                CONSTRAINT class_expanded_progress_pk2 UNIQUE (pp_search_id, strm)
);
CREATE INDEX class_expanded_progress_pp_search_id_strm_index ON public.class_expanded_progress USING btree (pp_search_id, strm);


-- public.class_schedule_data definition

-- Drop table

-- DROP TABLE public.class_schedule_data;

CREATE TABLE public.class_schedule_data (
                                            id serial4 NOT NULL,
                                            pp_search_id varchar(40) NOT NULL,
                                            coursetitle varchar(256) NULL,
                                            class_number_code varchar NULL,
                                            enrl_tot int4 NULL,
                                            facility_id varchar NULL,
                                            bldg_num varchar NULL,
                                            bldg_code varchar NULL,
                                            room_num varchar NULL,
                                            start_date date NULL,
                                            end_date date NULL,
                                            meeting_time_start time NULL,
                                            meeting_time_end time NULL,
                                            last_updated timestamptz NULL DEFAULT now(),
                                            versioned timestamptz NULL,
                                            monday int4 NULL,
                                            wednesday int4 NULL,
                                            thursday int4 NULL,
                                            friday int4 NULL,
                                            saturday int4 NULL,
                                            sunday int4 NULL,
                                            strm int4 NULL,
                                            campus int4 NULL,
                                            tuesday int4 NULL,
                                            last_dt_to_expanded timestamptz NULL,
                                            CONSTRAINT class_schedule_data_pk PRIMARY KEY (id)
);
CREATE INDEX class_schedule_data_ppsearchid__index ON public.class_schedule_data USING btree (pp_search_id);


-- public.config definition

-- Drop table

-- DROP TABLE public.config;

CREATE TABLE public.config (
                               id int8 NOT NULL GENERATED ALWAYS AS IDENTITY( INCREMENT BY 1 MINVALUE 1 MAXVALUE 9223372036854775807 START 1 CACHE 1 NO CYCLE),
                               config_key varchar NOT NULL,
                               config_scope varchar NOT NULL,
                               config_value varchar NOT NULL,
                               CONSTRAINT config_pk PRIMARY KEY (id),
                               CONSTRAINT "key-scope-uk" UNIQUE (config_key, config_scope)
);
CREATE INDEX key_index ON public.config USING btree (config_key);


-- public.config_tables definition

-- Drop table

-- DROP TABLE public.config_tables;

CREATE TABLE public.config_tables (
                                      id int8 NOT NULL GENERATED ALWAYS AS IDENTITY( INCREMENT BY 1 MINVALUE 1 MAXVALUE 9223372036854775807 START 1 CACHE 1 NO CYCLE),
                                      table_name varchar NOT NULL,
                                      "desc" text NULL,
                                      CONSTRAINT config_tables_pk PRIMARY KEY (id),
                                      CONSTRAINT config_tables_uk UNIQUE (table_name)
);


-- public.exam_schedule_data definition

-- Drop table

-- DROP TABLE public.exam_schedule_data;

CREATE TABLE public.exam_schedule_data (
                                           id serial4 NOT NULL,
                                           pp_search_id varchar NOT NULL,
                                           strm int4 NULL,
                                           facility_descr varchar NULL,
                                           facility_id varchar NULL,
                                           dt_start timestamptz NULL,
                                           dt_end timestamptz NULL,
                                           last_updated timestamptz NULL DEFAULT now(),
                                           versioned timestamptz NULL,
                                           campus int4 NULL,
                                           CONSTRAINT exam_schedule_data_pk PRIMARY KEY (id)
);


-- public.expanded_schedule_data definition

-- Drop table

-- DROP TABLE public.expanded_schedule_data;

CREATE TABLE public.expanded_schedule_data (
                                               id serial4 NOT NULL,
                                               pp_search_id varchar(40) NULL,
                                               strm int4 NULL,
                                               facility_id varchar NULL,
                                               class_number_code varchar NULL,
                                               coursetitle varchar(256) NULL,
                                               enrl_tot int4 NULL,
                                               bldg_num varchar NULL,
                                               bldg_code varchar NULL,
                                               room_number varchar NULL,
                                               datetime_start timestamptz NULL,
                                               datetime_end timestamptz NULL,
                                               last_updated timestamptz NULL DEFAULT now(),
                                               campus int4 NULL,
                                               CONSTRAINT expanded_schedule_data_pk PRIMARY KEY (id)
);
CREATE INDEX expanded_schedule_multiple__index ON public.expanded_schedule_data USING btree (strm, pp_search_id, datetime_start, datetime_end);
CREATE UNIQUE INDEX idx_unique_exam ON public.expanded_schedule_data USING btree (pp_search_id, strm, datetime_start, datetime_end);


-- public.floors definition

-- Drop table

-- DROP TABLE public.floors;

CREATE TABLE public.floors (
                               id serial4 NOT NULL,
                               floor_designation varchar NOT NULL,
                               buildings_id int4 NOT NULL,
                               active bool NULL DEFAULT false,
                               CONSTRAINT floors_pk PRIMARY KEY (id),
                               CONSTRAINT floors_pk2 UNIQUE (buildings_id, floor_designation, active)
);


-- public.function_log definition

-- Drop table

-- DROP TABLE public.function_log;

CREATE TABLE public.function_log (
                                     id serial4 NOT NULL,
                                     logged_element varchar NULL,
                                     logged_date timestamptz NULL DEFAULT now(),
                                     tag varchar NULL,
                                     CONSTRAINT function_log_pk PRIMARY KEY (id)
);
CREATE INDEX function_log_tag_index ON public.function_log USING btree (tag);


-- public.import_log definition

-- Drop table

-- DROP TABLE public.import_log;

CREATE TABLE public.import_log (
                                   id serial4 NOT NULL,
                                   user_id int4 NULL DEFAULT 0,
                                   import_type varchar NOT NULL,
                                   import_data json NOT NULL,
                                   import_date timestamp NULL DEFAULT now()
);


-- public.logged_updates definition

-- Drop table

-- DROP TABLE public.logged_updates;

CREATE TABLE public.logged_updates (
                                       id serial4 NOT NULL,
                                       updated_table_name varchar NOT NULL,
                                       old_value varchar NULL,
                                       new_value varchar NULL,
                                       user_id int4 NULL,
                                       time_updated timestamp NULL DEFAULT now(),
                                       updated_table_id int4 NULL,
                                       column_name varchar NULL,
                                       common_name varchar NULL,
                                       CONSTRAINT logged_updates_pk PRIMARY KEY (id)
);


-- public.nces_4_2 definition

-- Drop table

-- DROP TABLE public.nces_4_2;

CREATE TABLE public.nces_4_2 (
                                 id int4 NOT NULL GENERATED ALWAYS AS IDENTITY( INCREMENT BY 1 MINVALUE 1 MAXVALUE 2147483647 START 1 CACHE 1 NO CYCLE),
                                 code varchar NOT NULL,
                                 space_use_name varchar NULL,
                                 category_id int4 NULL,
                                 CONSTRAINT nces_4_2_pk PRIMARY KEY (id)
);


-- public.nces_ashrae_xref definition

-- Drop table

-- DROP TABLE public.nces_ashrae_xref;

CREATE TABLE public.nces_ashrae_xref (
                                         ashrae_id int4 NOT NULL,
                                         nces_id int4 NOT NULL,
                                         CONSTRAINT nces_ashrae_xref_pk PRIMARY KEY (ashrae_id, nces_id)
);


-- public.nces_categories definition

-- Drop table

-- DROP TABLE public.nces_categories;

CREATE TABLE public.nces_categories (
                                        id serial4 NOT NULL,
                                        code varchar NULL,
                                        type_name varchar NULL,
                                        CONSTRAINT nces_categories_pk UNIQUE (code),
                                        CONSTRAINT nces_id_pk PRIMARY KEY (id)
);


-- public.room_zone_xback definition

-- Drop table

-- DROP TABLE public.room_zone_xback;

CREATE TABLE public.room_zone_xback (
                                        room_id int4 NOT NULL,
                                        zone_id int4 NOT NULL,
                                        area varchar NULL,
                                        population varchar NULL,
                                        uncertainty varchar NULL,
                                        building_id int4 NULL,
                                        id int4 NOT NULL DEFAULT nextval('room_zone_xref_id_seq'::regclass),
                                        pr_percent varchar NULL,
                                        CONSTRAINT room_zone_xref_pk PRIMARY KEY (zone_id, room_id)
);


-- public.room_zone_xref definition

-- Drop table

-- DROP TABLE public.room_zone_xref;

CREATE TABLE public.room_zone_xref (
                                       id serial4 NOT NULL,
                                       room_id int4 NOT NULL,
                                       zone_id int4 NOT NULL,
                                       pr_percent float8 NULL,
                                       xref_area float8 NULL,
                                       xref_population float8 NULL,
                                       CONSTRAINT room_zone_xtemp_pk UNIQUE (room_id, zone_id),
                                       CONSTRAINT rz_id PRIMARY KEY (id)
);
CREATE INDEX room_zone_xtemp_zone_id_room_id_index ON public.room_zone_xref USING btree (zone_id, room_id);


-- public.rooms definition

-- Drop table

-- DROP TABLE public.rooms;

CREATE TABLE public.rooms (
                              facility_id varchar NOT NULL,
                              room_name varchar NULL,
                              building_id int4 NOT NULL,
                              room_area numeric(8, 1) NULL,
                              ash61_cat_id int4 NULL,
                              room_population int4 NULL,
                              room_num varchar NULL,
                              floor_id int4 NULL,
                              id int4 NOT NULL DEFAULT nextval('rooms_inc_seq'::regclass),
                              uncert_amt int4 NULL,
                              rtype_code varchar NULL,
                              space_use_name varchar NULL,
                              active bool NULL DEFAULT false,
                              reservable bool NULL DEFAULT false,
                              CONSTRAINT rooms_facid_act_pk UNIQUE (facility_id, active),
                              CONSTRAINT rooms_pk_1 PRIMARY KEY (id)
);


-- public.rooms_bak definition

-- Drop table

-- DROP TABLE public.rooms_bak;

CREATE TABLE public.rooms_bak (
                                  facility_id varchar NULL,
                                  room_name varchar NULL,
                                  building_id int4 NULL,
                                  room_area numeric(8, 1) NULL,
                                  ash61_cat_id int4 NULL,
                                  room_population int4 NULL,
                                  room_num varchar NULL,
                                  floor_id int4 NULL,
                                  id serial4 NOT NULL,
                                  uncert_amt int4 NULL,
                                  rtype_code varchar NULL,
                                  space_use_name varchar NULL,
                                  hid varchar NULL,
                                  active bool NULL DEFAULT false,
                                  CONSTRAINT rooms_bak_pk PRIMARY KEY (id),
                                  CONSTRAINT rooms_facid_act_uk UNIQUE (active, facility_id)
);


-- public.rooms_test definition

-- Drop table

-- DROP TABLE public.rooms_test;

CREATE TABLE public.rooms_test (
                                   facility_id varchar NOT NULL,
                                   room_name varchar NULL,
                                   building_id int4 NOT NULL,
                                   room_area numeric(8, 1) NULL,
                                   ash61_cat_id int4 NULL,
                                   room_population int4 NULL,
                                   room_num varchar NULL,
                                   floor_id int4 NULL,
                                   id int4 NOT NULL DEFAULT nextval('rooms_new_id_seq'::regclass),
                                   rtype_code int4 NULL,
                                   space_use_name varchar NULL,
                                   CONSTRAINT rooms_pk PRIMARY KEY (id)
);


-- public.setpoint_expanded definition

-- Drop table

-- DROP TABLE public.setpoint_expanded;

CREATE TABLE public.setpoint_expanded (
                                          zone_name varchar NULL,
                                          facility_id varchar NULL,
                                          coursetitle varchar NULL,
                                          enrl_tot int4 NULL,
                                          pv float4 NULL,
                                          class_number_code varchar NULL,
                                          zone_id int8 NULL,
                                          id bigserial NOT NULL,
                                          effectivetime timestamp NULL,
                                          CONSTRAINT setpoint_expanded_pk PRIMARY KEY (id),
                                          CONSTRAINT setpoint_expanded_un UNIQUE (zone_name, effectivetime)
);


-- public.setpoint_write definition

-- Drop table

-- DROP TABLE public.setpoint_write;

CREATE TABLE public.setpoint_write (
                                       id serial4 NOT NULL,
                                       effectivetime timestamp NULL,
                                       uname text NULL,
                                       pv float4 NULL,
                                       dispatched bool NULL DEFAULT false
);


-- public.temp_trends definition

-- Drop table

-- DROP TABLE public.temp_trends;

CREATE TABLE public.temp_trends (
                                    ptid int4 NOT NULL,
                                    t timestamptz NOT NULL,
                                    pv float8 NOT NULL
);
CREATE UNIQUE INDEX idx_public_trends_key ON public.temp_trends USING btree (ptid, t);
CREATE INDEX public_trends_t_idx ON public.temp_trends USING btree (t DESC);


-- public.terms definition

-- Drop table

-- DROP TABLE public.terms;

CREATE TABLE public.terms (
                              id serial4 NOT NULL,
                              term_name varchar NULL,
                              term_code int4 NULL,
                              term_start date NULL,
                              term_end date NULL,
                              CONSTRAINT terms_pk PRIMARY KEY (id)
);
CREATE INDEX term_end__index ON public.terms USING btree (term_end);
CREATE INDEX term_start__index ON public.terms USING btree (term_start);


-- public.uncert_6_1_xref definition

-- Drop table

-- DROP TABLE public.uncert_6_1_xref;

CREATE TABLE public.uncert_6_1_xref (
                                        ashrae_id int4 NULL,
                                        uncert_id int4 NULL
);


-- public.uncertainty definition

-- Drop table

-- DROP TABLE public.uncertainty;

CREATE TABLE public.uncertainty (
                                    id serial4 NOT NULL,
                                    cat_61_id int4 NULL,
                                    u_desc varchar NULL,
                                    uncert_amt int4 NULL,
                                    CONSTRAINT uncertainty_pk PRIMARY KEY (id),
                                    CONSTRAINT uncertainty_pk2 UNIQUE (cat_61_id)
);


-- public.user_roles definition

-- Drop table

-- DROP TABLE public.user_roles;

CREATE TABLE public.user_roles (
                                   id int8 NOT NULL GENERATED ALWAYS AS IDENTITY( INCREMENT BY 1 MINVALUE 1 MAXVALUE 9223372036854775807 START 1 CACHE 1 NO CYCLE),
                                   role_name varchar NULL,
                                   CONSTRAINT roles_pk PRIMARY KEY (id)
);


-- public.users definition

-- Drop table

-- DROP TABLE public.users;

CREATE TABLE public.users (
                              id serial4 NOT NULL,
                              first_name varchar NULL,
                              last_name varchar NULL,
                              email varchar NULL,
                              "role" int4 NULL,
                              uid varchar NOT NULL,
                              CONSTRAINT users_pk PRIMARY KEY (id),
                              CONSTRAINT users_pk2 UNIQUE (uid)
);
CREATE INDEX uid__index ON public.users USING btree (uid);


-- public.weather_forecast definition

-- Drop table

-- DROP TABLE public.weather_forecast;

CREATE TABLE public.weather_forecast (
                                         id serial4 NOT NULL,
                                         forecast_date date NULL,
                                         datetimestamp timestamptz NULL DEFAULT now(),
                                         day_01 numeric NULL,
                                         day_02 numeric NULL,
                                         day_03 numeric NULL,
                                         day_04 numeric NULL,
                                         day_05 numeric NULL,
                                         day_06 numeric NULL,
                                         day_07 numeric NULL,
                                         day_08 numeric NULL,
                                         day_09 numeric NULL,
                                         day_10 numeric NULL,
                                         day_11 numeric NULL,
                                         day_12 numeric NULL,
                                         day_13 numeric NULL,
                                         day_14 numeric NULL,
                                         campus int4 NULL DEFAULT 1,
                                         CONSTRAINT weather_forecast_pk PRIMARY KEY (id),
                                         CONSTRAINT weather_forecast_pk_2 UNIQUE (forecast_date)
);


-- public.zones definition

-- Drop table

-- DROP TABLE public.zones;

CREATE TABLE public.zones (
                              id serial4 NOT NULL,
                              zone_name varchar NULL,
                              zone_code varchar NOT NULL,
                              building_id int4 NOT NULL,
                              ahu_name varchar NULL,
                              occ_sensor bool NULL,
                              active bool NULL DEFAULT false,
                              auto_mode bool NULL DEFAULT false,
                              CONSTRAINT zones_pk PRIMARY KEY (id),
                              CONSTRAINT zones_uniq_act_k UNIQUE (zone_name, active)
);
CREATE UNIQUE INDEX zones_zone_code_idx ON public.zones USING btree (zone_code);
CREATE UNIQUE INDEX zones_zone_name_idx ON public.zones USING btree (zone_name);