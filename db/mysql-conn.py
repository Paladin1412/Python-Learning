import pymysql

db = pymysql.connect(host='192.168.11.31',port=30001,user='michael',password='cccbbb',database='home')
with db.cursor() as cursor:
    cursor.execute("select * from lywebback_papers")
    ret = cursor.fetchall()
print(ret)
db.close()

