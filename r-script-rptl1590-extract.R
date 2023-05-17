library(pdftools)
library(tidyverse)
library(sf)

pdf.text <- pdftools::pdf_text('knox_2022_final_roll.pdf')

tax.rolls <- pdf.text %>% 
  str_split('\\*{5,}') %>% 
  map(~str_trim(.))

tax.rolls <- unlist(tax.rolls, recursive = F) 

tax.rolls <- 
  tax.rolls[str_length(tax.rolls) > 50 &
          !grepl('STATE OF NEW YORK',
                tax.rolls) ]

tax.rolls.formatted <- 
  tibble(text = tax.rolls) %>%
  transmute(taxid = str_extract(text, '^.*?\\n.(.*?) ', group = 1),
            property.address = str_extract(text, '^(.*?)(\\n| {2,})', group = 1),
            owner = str_extract(text, '(.*?\n){2}(.*?) {2}', group=2),
            acres = str_extract(text, 'ACRES *?((\\d|.)*) ', group = 1) %>%
              parse_number,
            property.type = str_extract(text, '^.*?\\n.*? {2,}(.*?) {2,}', group = 1),
            school.district = str_extract(text, '(.*?\n){2}(.*?) {2,}(.*?) {2,}', group=3),
            deed.book = str_extract(text, 'DEED BOOK(.*?)(\n| {6,})', group=1) %>% str_replace('\\s{2,}',' ') %>% str_trim,
            owner.address = str_extract(text, '(.*?\n){3}(.*?) {2}', group=2),
            owner.address2 = str_extract(text, '(.*?\n){4}(.*?) {2}', group=2),  
            owner.address3 = str_extract(text, '(.*?\n){5}(.*?) {2}', group=2), 
            land.assessment = str_extract(text, '(.*?\n){2}(.*?) {3,}(.*?) {2,}(.*?) {2,}', group=4) %>%
              parse_number,
            total.assessment = str_extract(text, 'TOWN.*?VALUE {4,}(.*?)\n', group=1) %>%
              parse_number,
            full.market.value = str_extract(text, 'FULL.*?MARKET.*?VALUE *?(.*?)(\\n|$)', group=1) %>%
              parse_number,
            town.taxable.value = str_extract(text, 'TOWN.*?TAXABLE.*?VALUE(.*?)\n', group = 1) %>%
              parse_number,
            city.taxable.value = str_extract(text, 'CITY.*?TAXABLE.*?VALUE(.*?)\n', group = 1) %>%
              parse_number,
            county.taxable.value = str_extract(text, 'COUNTY TAXABLE VALUE(.*?)\n', group = 1) %>%
              parse_number,
            school.taxable.value = str_extract(text, 'SCHOOL.*?TAXABLE.*?VALUE(.*?)\n', group = 1) %>%
              parse_number,
            ag.dist.law = paste(str_extract(text,
                                      '(MAY BE SUBJECT TO PAYMENT)', group=1),
                                str_extract(text,
                                      '(UNDER .*?) {2,}', group=1)
            ),
            north =  str_extract(text, 'NRTH-(\\d*)', group=1) %>%
              parse_number %>% replace_na(0),
            east =  str_extract(text, 'EAST-(\\d*)', group=1) %>%
              parse_number  %>% replace_na(0),
            ) %>%
# ny long island crs 32014, ny east crs 32015, ny central crs 32016, ny west crs 32017
  st_as_sf(coords=c('east','north'), crs=32015) 

tax.rolls.formatted %>%
  write_sf('/tmp/taxroll.shp')

tax.rolls.formatted %>%
  write_sf('/tmp/taxroll.csv')

