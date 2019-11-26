class User:
    def __init__(self,name,age,info={}):
        self.name=name
        self.age = age
        self.info = info

    def __getattr__(self, item):

        return self.info[item]

    # def __getattribute__(self, item):
    #     return "bobby"
if __name__ == '__main__':
    user = User("n",12,info={"comp":"ebay"})
    print(user.comp)