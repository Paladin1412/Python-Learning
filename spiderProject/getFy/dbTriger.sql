DROP TRIGGER IF EXISTS auto_sum;
create trigger auto_sum
    after update
    on pneumonia_record.all_city_new
    for each row
    begin
        set @city_num_all_sum=(select count(city_name) from pneumonia_record.all_city_new);
        set @death_all_count=(select sum(death_num) from pneumonia_record.all_city_new);
        set @confirm_num_all_count=(select sum(confirm_num) from pneumonia_record.all_city_new);
        set @cure_num_all =(select sum(cure_num) from pneumonia_record.all_city_new);
        insert into pneumonia_record.static_num(city_num_all, confirm_num_all, death_num_all, cure_num_all) VALUES (@city_num_all_sum,@confirm_num_all_count,@death_all_count,@cure_num_all);
    end;