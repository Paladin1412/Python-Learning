import pymysql
import random

group = ['风', '林', '山']

target = []
for i in range(300):
    group_tmp = group[random.randint(0, 2)]
    tmp_target = ('t' + str(i), group_tmp, random.randint(-10, 10))
    target.append(tmp_target)

db = pymysql.connect(host='michael.qicp.net', port=60001, user='michael', password='cccbbb', database='stzb')
with db.cursor() as cursor:
    sql = "INSERT INTO alliance_member(member_name,member_group,member_mark) values (%s,%s,%s)"
    cursor.executemany(sql, tuple(target))
    db.commit()
db.close()
