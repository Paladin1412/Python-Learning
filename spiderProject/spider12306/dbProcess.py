import pymysql
import json


class DbProcess:
    def __init__(self,path=None):
        if not path:
            path="db.json"
        with open(path,"r") as fp:
            _conf = json.load(fp)
            _dbUser = _conf['user']
            _dbPwd = _conf['pwd']
            _dbPort = _conf['port']
            _dbHost = _conf['host']
            _dbDb = _conf['db']
            self.db = pymysql.connect(host=_dbHost,password=_dbPwd,user=_dbUser,port=_dbPort,database=_dbDb)

    def searchDb(self,sql):
        with self.db.cursor(cursor=pymysql.cursors.DictCursor) as cursor:
            cursor.execute(sql)
            ret = cursor.fetchall()
        self.db.close()
        return ret

    # def searchDb(self,value):
    #     with self.db.cursor(cursor=pymysql.cursors.DictCursor) as cursor:
    #         for k, v in city.items():
    #             sql = "INSERT INTO city_abbreviation (city,abbreviation) VALUES ('{}','{}')".format(k,v)
    #             print(sql)
    #             cursor.execute(sql)
    #         db.commit()
    #     db.close()
