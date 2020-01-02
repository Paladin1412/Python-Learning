class User:
    def __init__(self,name,age):
        self.name=name
        self.age = age

    @property
    def ten_age(self):
        return self.age*10

    @ten_age.setter
    def ten_age(self,value):
        self.age = value

if __name__ == '__main__':
    user = User("n",12)
    user.ten_age=10
    print(user.ten_age)